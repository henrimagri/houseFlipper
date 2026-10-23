export function passwordPairError(password: string, confirmation: string): string {
  if (confirmation && password !== confirmation) return 'As senhas não coincidem.';
  if (new TextEncoder().encode(password).length > 72) return 'A senha deve ocupar no máximo 72 bytes.';
  return '';
}
export function registrationErrorStep(fields: string[]): number {
  const steps = fields.map(field => field.startsWith('address') ? 3 : ['name','email','tenant_nome','password','password_confirmation'].includes(field) ? 1 : ['professional_name','city','company_name','region_preference','usage_goal','phone','creci'].includes(field) ? 2 : field === 'intended_product' ? 0 : 4);
  return Math.min(...steps, 4);
}
export function missingRegistrationField(step: number, person: Record<string,string>, extra: Record<string,string>, address: Record<string,string>, product: string): string {
  const fields = step === 1 ? ['name','email','tenant_nome','password','password_confirmation'].map(key => person[key]) : step === 2 ? [person.phone,...(product === 'corretor' ? ['professional_name','city'] : product === 'imobiliaria' ? ['company_name','city'] : ['region_preference','usage_goal']).map(key => extra[key])] : step === 3 ? ['postal_code','street','number','complement','neighborhood','city','state'].map(key => address[key]) : [];
  return fields.some(value => !value?.trim()) ? 'Preencha todos os campos obrigatórios identificados com *.' : '';
}
