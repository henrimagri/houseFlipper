export function appendOpportunityFields(payload: FormData, fields: Record<string, string>): void {
  Object.entries(fields).forEach(([key, value]) => {
    payload.append(key, value);
  });
}
