export default function ErrorState({ message, onRetry }: { message: string; onRetry?: () => void }) {
  return (
    <div role="alert" className="rounded-xl border border-red-200 bg-red-50 dark:bg-red-950 dark:border-red-900 p-4 text-sm text-red-800 dark:text-red-200">
      <p>{message}</p>
      {onRetry && <button type="button" onClick={onRetry} className="mt-2 underline font-semibold">Tentar novamente</button>}
    </div>
  );
}
