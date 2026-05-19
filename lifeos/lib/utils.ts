export function formatDate(date: Date | string): string {
  const d = new Date(date);
  return d.toLocaleDateString("en-US", {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
  });
}

export function toDateOnly(date: Date | string): Date {
  const d = new Date(date);
  return new Date(d.getFullYear(), d.getMonth(), d.getDate());
}

export function todayUTC(): Date {
  const now = new Date();
  return new Date(now.getFullYear(), now.getMonth(), now.getDate());
}

export function startOfWeek(date: Date): Date {
  const d = new Date(date);
  const day = d.getDay();
  const diff = d.getDate() - day;
  return new Date(d.setDate(diff));
}

export function daysAgo(n: number): Date {
  const d = new Date();
  d.setDate(d.getDate() - n);
  return new Date(d.getFullYear(), d.getMonth(), d.getDate());
}

export function statusColor(
  value: number | null | undefined,
  low: number,
  high: number,
  higherIsBetter = true
): "green" | "yellow" | "red" | "gray" {
  if (value == null) return "gray";
  if (higherIsBetter) {
    if (value >= high) return "green";
    if (value >= low) return "yellow";
    return "red";
  } else {
    if (value <= low) return "green";
    if (value <= high) return "yellow";
    return "red";
  }
}

export function pearsonCorrelation(xs: number[], ys: number[]): number {
  const n = xs.length;
  if (n < 2) return 0;
  const meanX = xs.reduce((a, b) => a + b, 0) / n;
  const meanY = ys.reduce((a, b) => a + b, 0) / n;
  const num = xs.reduce((s, x, i) => s + (x - meanX) * (ys[i] - meanY), 0);
  const denX = Math.sqrt(xs.reduce((s, x) => s + (x - meanX) ** 2, 0));
  const denY = Math.sqrt(ys.reduce((s, y) => s + (y - meanY) ** 2, 0));
  if (denX === 0 || denY === 0) return 0;
  return num / (denX * denY);
}
