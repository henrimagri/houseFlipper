export function periodicReturns(totalRoiPercentage: unknown, durationMonths: unknown) {
  const roi = Number(totalRoiPercentage);
  const months = Math.max(1, Math.trunc(Number(durationMonths)) || 1);
  if (!Number.isFinite(roi)) return { monthly: 0, annualized: 0 };

  const growthFactor = Math.max(0, 1 + roi / 100);
  if (growthFactor === 0) return { monthly: -100, annualized: -100 };

  return {
    monthly: (growthFactor ** (1 / months) - 1) * 100,
    annualized: (growthFactor ** (12 / months) - 1) * 100,
  };
}
