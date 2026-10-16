interface PageStubProps {
  titulo: string;
  descricao?: string;
}

export default function PageStub({ titulo, descricao = 'Em breve.' }: PageStubProps) {
  return (
    <div className="bg-white rounded-xl border border-gray-200 p-10 shadow-sm text-center">
      <h2 className="text-xl font-semibold text-gray-900">{titulo}</h2>
      <p className="text-sm text-gray-500 mt-2">{descricao}</p>
    </div>
  );
}
