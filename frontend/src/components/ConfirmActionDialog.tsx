import { useEffect, useId, useRef } from 'react';
import { AlertTriangle } from 'lucide-react';

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
  const dialog = useRef<HTMLDialogElement>(null);
  const id = useId();
  const titleId = `${id}-title`;
  const descriptionId = `${id}-description`;

  useEffect(() => {
    const element = dialog.current;
    const trigger = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    element?.showModal();
    return () => { element?.close(); trigger?.focus(); };
  }, []);

  return <dialog ref={dialog} aria-labelledby={titleId} aria-describedby={descriptionId} onCancel={event => { if (pending) event.preventDefault(); else onCancel(); }} className="p-0 w-[calc(100%-2rem)] max-w-lg rounded-3xl bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 backdrop:bg-slate-950/60 backdrop:backdrop-blur-xs">
    <div className="p-6">
      <div className="w-12 h-12 rounded-2xl bg-red-100 dark:bg-red-950 text-red-700 dark:text-red-300 grid place-items-center"><AlertTriangle className="w-6 h-6" /></div>
      <h2 id={titleId} className="text-xl font-black mt-4">{title}</h2>
      <p id={descriptionId} className="text-sm text-slate-600 dark:text-slate-300 mt-2">{description}</p>
      <div className="flex flex-col-reverse sm:flex-row sm:justify-end gap-3 mt-6">
        <button type="button" autoFocus disabled={pending} onClick={onCancel} className="border border-slate-300 dark:border-slate-700 px-5 py-3 rounded-xl font-bold disabled:opacity-50">Cancelar</button>
        <button type="button" disabled={pending} onClick={() => void onConfirm()} className="bg-red-600 hover:bg-red-700 text-white px-5 py-3 rounded-xl font-bold disabled:opacity-50">{pending ? pendingLabel : actionLabel}</button>
      </div>
    </div>
  </dialog>;
}
