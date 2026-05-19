type Status = "green" | "yellow" | "red" | "gray";

interface Props {
  label: string;
  value: string | number | null | undefined;
  unit?: string;
  status?: Status;
  subtitle?: string;
}

const statusStyles: Record<Status, string> = {
  green: "border-emerald-500/40 bg-emerald-950/30",
  yellow: "border-yellow-500/40 bg-yellow-950/30",
  red: "border-red-500/40 bg-red-950/30",
  gray: "border-slate-700 bg-slate-900/50",
};

const dotStyles: Record<Status, string> = {
  green: "bg-emerald-400",
  yellow: "bg-yellow-400",
  red: "bg-red-400",
  gray: "bg-slate-600",
};

export default function MetricCard({ label, value, unit, status = "gray", subtitle }: Props) {
  if (value == null) return null;
  return (
    <div className={`rounded-xl border p-4 ${statusStyles[status]}`}>
      <div className="flex items-center gap-2 mb-1">
        <div className={`w-2 h-2 rounded-full ${dotStyles[status]}`} />
        <span className="text-xs text-slate-400 uppercase tracking-wider">{label}</span>
      </div>
      <div className="flex items-baseline gap-1">
        <span className="text-2xl font-bold text-white">{value}</span>
        {unit && <span className="text-sm text-slate-400">{unit}</span>}
      </div>
      {subtitle && <p className="text-xs text-slate-500 mt-1">{subtitle}</p>}
    </div>
  );
}
