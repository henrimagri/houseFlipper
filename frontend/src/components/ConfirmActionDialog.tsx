import { AlertTriangle } from 'lucide-react';
import DialogShell from '@/ui/DialogShell';

type ConfirmActionDialogProps = {
  title: string;
  description: string;
  actionLabel: string;
  pendingLabel?: string;
  pending?: boolean;
  onCancel: () => void;
  onConfirm: () => void | Promise<void>;
};

export default function ConfirmActionDialog({ title, description, actionLabel, pendingLabel = 'Processando...', pending = false, onCancel, onConfirm }: ConfirmActionDialogProps) {
  return <DialogShell title={title} description={description} onClose={onCancel} closeDisabled={pending} size="sm" footer={<div className="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"><button type="button" autoFocus disabled={pending} onClick={onCancel} className="rounded-xl border border-slate-300 px-5 py-3 font-bold disabled:opacity-50 dark:border-slate-700">Cancelar</button><button type="button" disabled={pending} onClick={() => void onConfirm()} className="rounded-xl bg-red-600 px-5 py-3 font-bold text-white hover:bg-red-700 disabled:opacity-50">{pending ? pendingLabel : actionLabel}</button></div>}>
    <div>
      <div className="w-12 h-12 rounded-2xl bg-red-100 dark:bg-red-950 text-red-700 dark:text-red-300 grid place-items-center"><AlertTriangle className="w-6 h-6" /></div>
    </div>
  </DialogShell>;
}
