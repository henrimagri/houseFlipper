export default function LoadingSkeleton({ label = 'Carregando...' }: { label?: string }) {
  return (
    <div role="status" className="space-y-3 animate-pulse">
      <span className="sr-only">{label}</span>
      <div className="h-24 rounded-xl bg-gray-200 dark:bg-slate-800" />
      <div className="grid md:grid-cols-3 gap-3">
        <div className="h-28 rounded-xl bg-gray-200 dark:bg-slate-800" />
        <div className="h-28 rounded-xl bg-gray-200 dark:bg-slate-800" />
        <div className="h-28 rounded-xl bg-gray-200 dark:bg-slate-800" />
      </div>
    </div>
  );
}
