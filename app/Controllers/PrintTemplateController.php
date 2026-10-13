<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\PrintDoc;
use App\Models\PrintTemplate;
use Core\Audit;
use Core\Auth;
use Core\Controller;
use Core\DB;

/** Settings → Print templates: several designed layouts per document, one of them the default. */
final class PrintTemplateController extends Controller
{
    private function tid(): int
    {
        return (int)Auth::tenantId();
    }

    private function doc(): string
    {
        $doc = (string)($_POST['doc'] ?? $_GET['doc'] ?? '');
        return isset(PrintTemplate::DOCS[$doc]) ? $doc : $this->notFound();
    }

    /** The designer: the vocabulary of the document and the layout being edited ("std" or empty id = a new one starting from Standard). */
    public function design(): void
    {
        $doc = $this->doc();
        $id = (string)($_GET['id'] ?? '');
        $found = $id !== '' && $id !== PrintDoc::STD ? (PrintDoc::find($doc, $id) ?? $this->notFound()) : null;
        $base = $found ?? PrintDoc::find($doc, PrintDoc::STD);
        $this->view('app/settings/print_design', [
            'title' => 'Print template', 'doc' => $doc, 'docs' => PrintTemplate::DOCS, 'spec' => PrintDoc::SPEC[$doc], 'sizes' => PrintDoc::SIZES, 'fonts' => PrintDoc::FONTS,
            'id' => $found ? $found['id'] : null, 'name' => $found ? $found['name'] : '', 'cfg' => $base['cfg'], 'latest' => PrintDoc::latestId($doc), 'head' => PrintTemplate::head(),
            'isDefault' => $found ? (bool)DB::val('SELECT is_default FROM print_templates WHERE tenant_id = ? AND id = ?', [$this->tid(), (int)$found['id']]) : false,
        ]);
    }

    public function save(): void
    {
        $doc = $this->doc();
        $d = $this->input();
        $name = trim((string)($d['name'] ?? ''));
        $back = 'settings/templates?doc=' . $doc;
        if ($name === '' || mb_strlen($name) > 80) { flash('danger', 'Give the template a name (up to 80 characters).'); redirect($back); }
        $cfg = $d['cfg'] ?? null;
        if (is_string($cfg)) $cfg = json_decode($cfg, true);
        if (!is_array($cfg)) { flash('danger', 'Nothing to save — please try again.'); redirect($back); }
        $cfg = PrintDoc::clean($doc, $cfg);
        $json = (string)json_encode($cfg, JSON_UNESCAPED_UNICODE);
        $t = $this->tid();
        $id = (int)($d['id'] ?? 0);
        if (strtolower($name) === 'standard') { flash('danger', '"Standard" is the built-in template; choose another name.'); redirect($back); }
        if (DB::val('SELECT 1 FROM print_templates WHERE tenant_id = ? AND doc = ? AND name = ? AND id <> ?', [$t, $doc, $name, $id])) { flash('danger', 'A template with that name already exists.'); redirect($back); }
        if ($id) {
            if (!DB::val('SELECT 1 FROM print_templates WHERE tenant_id = ? AND doc = ? AND id = ?', [$t, $doc, $id])) $this->notFound();
            DB::run('UPDATE print_templates SET name = ?, config = ? WHERE tenant_id = ? AND id = ?', [$name, $json, $t, $id]);
        } else {
            if ((int)DB::val('SELECT COUNT(*) FROM print_templates WHERE tenant_id = ? AND doc = ?', [$t, $doc]) >= PrintDoc::MAX_PER_DOC) {
                flash('danger', 'You can keep up to ' . PrintDoc::MAX_PER_DOC . ' templates for one document. Delete one you no longer use.'); redirect($back);
            }
            $id = DB::insert('print_templates', ['tenant_id' => $t, 'doc' => $doc, 'name' => $name, 'config' => $json, 'created_by' => Auth::user()['id'] ?? null]);
        }
        if (!empty($d['make_default'])) $this->setDefault($doc, $id);
        Audit::log('print_template_save', 'print_template', $id, $name);
        flash('success', 'Template saved.');
        redirect("settings/templates/design?doc=$doc&id=$id");
    }

    private function setDefault(string $doc, ?int $id): void
    {
        $t = $this->tid();
        DB::run('UPDATE print_templates SET is_default = 0 WHERE tenant_id = ? AND doc = ?', [$t, $doc]);
        if ($id) DB::run('UPDATE print_templates SET is_default = 1 WHERE tenant_id = ? AND doc = ? AND id = ?', [$t, $doc, $id]);
    }

    public function makeDefault(string $id): void
    {
        $doc = $this->doc();
        if ($id === PrintDoc::STD) $this->setDefault($doc, null);
        else {
            if (!DB::val('SELECT 1 FROM print_templates WHERE tenant_id = ? AND doc = ? AND id = ?', [$this->tid(), $doc, (int)$id])) $this->notFound();
            $this->setDefault($doc, (int)$id);
        }
        Audit::log('print_template_default', 'print_template', is_numeric($id) ? (int)$id : null, $doc);
        flash('success', 'Default template changed.');
        redirect("settings/templates?doc=$doc");
    }

    public function duplicate(string $id): void
    {
        $doc = $this->doc(); $t = $this->tid();
        $src = PrintDoc::find($doc, $id) ?? $this->notFound();
        if ((int)DB::val('SELECT COUNT(*) FROM print_templates WHERE tenant_id = ? AND doc = ?', [$t, $doc]) >= PrintDoc::MAX_PER_DOC) { flash('danger', 'Too many templates for this document. Delete one first.'); redirect("settings/templates?doc=$doc"); }
        $base = $src['builtin'] ? 'Standard copy' : $src['name'] . ' copy';
        $name = mb_substr($base, 0, 74); $n = 1;
        while (DB::val('SELECT 1 FROM print_templates WHERE tenant_id = ? AND doc = ? AND name = ?', [$t, $doc, $name])) $name = mb_substr($base, 0, 70) . ' ' . (++$n);
        $new = DB::insert('print_templates', ['tenant_id' => $t, 'doc' => $doc, 'name' => $name, 'config' => (string)json_encode($src['cfg'], JSON_UNESCAPED_UNICODE), 'created_by' => Auth::user()['id'] ?? null]);
        Audit::log('print_template_copy', 'print_template', $new, $name);
        flash('success', 'Template copied.');
        redirect("settings/templates/design?doc=$doc&id=$new");
    }

    public function destroy(string $id): void
    {
        $doc = $this->doc(); $t = $this->tid();
        $row = DB::one('SELECT id, name FROM print_templates WHERE tenant_id = ? AND doc = ? AND id = ?', [$t, $doc, (int)$id]) ?? $this->notFound();
        DB::run('DELETE FROM print_templates WHERE tenant_id = ? AND id = ?', [$t, $row['id']]);   // if it was the default, Standard is the default again
        Audit::log('print_template_delete', 'print_template', (int)$row['id'], $row['name']);
        flash('success', 'Template deleted.');
        redirect("settings/templates?doc=$doc");
    }
}
