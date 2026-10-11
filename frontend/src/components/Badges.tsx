import { useSession } from '../lib/session';
import { payState } from '../lib/docs';
import { Badge } from './ui';

export function PoBadge({ status }: { status: string }) {
  const { session } = useSession();
  const [label, tone] = session?.poStatus?.[status] ?? [status, 'secondary'];
  return <Badge tone={tone}>{label}</Badge>;
}

export function PayBadge({ row, state }: { row?: { total: any; returned_amount: any; paid_amount: any }; state?: string }) {
  const { session } = useSession();
  const k = state ?? (row ? payState(row) : 'unpaid');
  const [label, tone] = session?.payStatus?.[k] ?? [k, 'secondary'];
  return <Badge tone={tone}>{label}</Badge>;
}

export function SoBadge({ status }: { status: string }) {
  const { session } = useSession();
  const [label, tone] = session?.soStatus?.[status] ?? [status, 'secondary'];
  return <Badge tone={tone}>{label}</Badge>;
}
