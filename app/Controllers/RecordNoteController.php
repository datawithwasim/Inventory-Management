<?php
declare(strict_types=1);

namespace App\Controllers;

use Core\Audit;
use Core\Auth;
use Core\Controller;
use Core\DB;
use Core\RecordView;

/** Notes on an item, customer or supplier. */
final class RecordNoteController extends Controller
{
    private const URLS = ['item' => 'items', 'customer' => 'customers', 'supplier' => 'suppliers'];

    private function guard(string $entity): void
    {
        if (!isset(RecordView::META[$entity]) || !Auth::can(RecordView::META[$entity][0])) $this->forbidden();
    }

    public function store(string $entity, string $id): void
    {
        $this->guard($entity);
        $t = (int)Auth::tenantId();
        $table = RecordView::META[$entity][1];
        if (!DB::val("SELECT 1 FROM `$table` WHERE tenant_id = ? AND id = ?", [$t, (int)$id])) $this->notFound();
        $body = trim((string)($this->input()['body'] ?? ''));
        $back = self::URLS[$entity] . "/" . (int)$id;
        if ($body === '') { flash('danger', 'Write something in the note first.'); redirect($back . '#notes'); }
        if (mb_strlen($body) > 1500) { flash('danger', 'The note is too long (max 1500 characters).'); redirect($back . '#notes'); }
        DB::insert('record_notes', ['tenant_id' => $t, 'entity' => $entity, 'entity_id' => (int)$id, 'body' => $body, 'created_by' => Auth::user()['id']]);
        Audit::log($entity . '_note', $entity, (int)$id, mb_substr($body, 0, 80));
        flash('success', 'Note added.');
        redirect($back . '#notes');
    }

    public function destroy(string $noteId): void
    {
        $t = (int)Auth::tenantId();
        $n = DB::one('SELECT * FROM record_notes WHERE tenant_id = ? AND id = ?', [$t, (int)$noteId]) ?? $this->notFound();
        $this->guard((string)$n['entity']);
        if ((int)$n['created_by'] !== (int)Auth::user()['id'] && !Auth::can('settings.edit')) $this->forbidden();
        DB::run('DELETE FROM record_notes WHERE tenant_id = ? AND id = ?', [$t, $n['id']]);
        flash('success', 'Note removed.');
        redirect(self::URLS[$n['entity']] . '/' . (int)$n['entity_id'] . '#notes');
    }
}
