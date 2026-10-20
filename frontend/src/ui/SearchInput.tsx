import AppSearchInput from './AppSearchInput';

export default function SearchInput({ value, onChange, placeholder = 'Buscar', label = 'Buscar' }: { value: string; onChange: (value: string) => void; placeholder?: string; label?: string }) {
  return <AppSearchInput className="min-w-[12rem] flex-1" value={value} onChange={onChange} placeholder={placeholder} label={label} />;
}
