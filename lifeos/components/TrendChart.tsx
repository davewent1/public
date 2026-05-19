"use client";

import {
  LineChart,
  Line,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
  ReferenceLine,
} from "recharts";

interface MetricConfig {
  key: string;
  label: string;
  color: string;
  goalLow?: number;
  goalHigh?: number;
}

interface Props {
  data: Record<string, number | string | null>[];
  metrics: MetricConfig[];
}

const CustomTooltip = ({ active, payload, label }: any) => {
  if (!active || !payload?.length) return null;
  return (
    <div className="bg-slate-800 border border-slate-700 rounded-lg p-3 text-xs">
      <p className="text-slate-400 mb-1">{label}</p>
      {payload.map((p: any) => (
        <p key={p.dataKey} style={{ color: p.color }}>
          {p.name}: {p.value != null ? Number(p.value).toFixed(1) : "—"}
        </p>
      ))}
    </div>
  );
};

export default function TrendChart({ data, metrics }: Props) {
  return (
    <ResponsiveContainer width="100%" height={280}>
      <LineChart data={data} margin={{ top: 5, right: 5, left: -20, bottom: 5 }}>
        <CartesianGrid strokeDasharray="3 3" stroke="#1e293b" />
        <XAxis
          dataKey="date"
          tick={{ fill: "#64748b", fontSize: 11 }}
          tickLine={false}
          axisLine={{ stroke: "#1e293b" }}
        />
        <YAxis
          tick={{ fill: "#64748b", fontSize: 11 }}
          tickLine={false}
          axisLine={false}
        />
        <Tooltip content={<CustomTooltip />} />
        {metrics.map((m) => (
          <Line
            key={m.key}
            type="monotone"
            dataKey={m.key}
            name={m.label}
            stroke={m.color}
            strokeWidth={2}
            dot={false}
            connectNulls={false}
          />
        ))}
        {metrics[0]?.goalLow != null && (
          <ReferenceLine y={metrics[0].goalLow} stroke="#334155" strokeDasharray="4 4" />
        )}
        {metrics[0]?.goalHigh != null && (
          <ReferenceLine y={metrics[0].goalHigh} stroke="#334155" strokeDasharray="4 4" />
        )}
      </LineChart>
    </ResponsiveContainer>
  );
}
