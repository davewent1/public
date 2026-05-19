"use client";

import { useState, useMemo } from "react";
import {
  ScatterChart,
  Scatter,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  ResponsiveContainer,
} from "recharts";
import { pearsonCorrelation } from "@/lib/utils";

const METRICS = [
  { key: "sleepScore", label: "Sleep Score" },
  { key: "sleepHours", label: "Sleep Hours" },
  { key: "hrv", label: "HRV" },
  { key: "restingHR", label: "Resting HR" },
  { key: "steps", label: "Steps" },
  { key: "stressScore", label: "Stress Score" },
  { key: "caloriesIn", label: "Calories In" },
  { key: "proteinG", label: "Protein (g)" },
  { key: "mood", label: "Mood" },
  { key: "energy", label: "Energy" },
  { key: "weight", label: "Weight" },
  { key: "fastingHours", label: "Fasting Hours" },
];

export default function CorrelationExplorer({ data }: { data: Record<string, any>[] }) {
  const [xKey, setXKey] = useState("sleepScore");
  const [yKey, setYKey] = useState("mood");

  const { scatterData, correlation, summary } = useMemo(() => {
    const pairs = data.filter((d) => d[xKey] != null && d[yKey] != null);
    const xs = pairs.map((d) => d[xKey] as number);
    const ys = pairs.map((d) => d[yKey] as number);
    const r = pearsonCorrelation(xs, ys);
    const xLabel = METRICS.find((m) => m.key === xKey)?.label ?? xKey;
    const yLabel = METRICS.find((m) => m.key === yKey)?.label ?? yKey;

    let summaryText = "";
    if (pairs.length >= 5) {
      const meanX = xs.reduce((a, b) => a + b, 0) / xs.length;
      const meanY = ys.reduce((a, b) => a + b, 0) / ys.length;
      const highXIndices = xs.reduce((acc: number[], x, i) => {
        if (x > meanX) acc.push(i);
        return acc;
      }, []);
      if (highXIndices.length > 0) {
        const avgYwhenHighX =
          highXIndices.reduce((s, i) => s + ys[i], 0) / highXIndices.length;
        const diff = avgYwhenHighX - meanY;
        const direction = diff > 0 ? "higher" : "lower";
        summaryText = `When ${xLabel} is above average, ${yLabel} averages ${Math.abs(diff).toFixed(1)} points ${direction}.`;
      }
    }

    return {
      scatterData: pairs.map((d) => ({ x: d[xKey], y: d[yKey] })),
      correlation: r,
      summary: summaryText,
    };
  }, [data, xKey, yKey]);

  const selectClass =
    "bg-slate-800 border border-slate-700 text-white text-sm rounded-lg px-3 py-2 focus:outline-none focus:border-blue-500";

  const rStrength =
    Math.abs(correlation) > 0.7
      ? "strong"
      : Math.abs(correlation) > 0.4
      ? "moderate"
      : "weak";

  const rColor =
    Math.abs(correlation) > 0.7
      ? "text-emerald-400"
      : Math.abs(correlation) > 0.4
      ? "text-yellow-400"
      : "text-slate-400";

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap gap-4 items-end">
        <div>
          <label className="text-xs text-slate-500 block mb-1">X Axis</label>
          <select value={xKey} onChange={(e) => setXKey(e.target.value)} className={selectClass}>
            {METRICS.map((m) => (
              <option key={m.key} value={m.key}>{m.label}</option>
            ))}
          </select>
        </div>
        <div>
          <label className="text-xs text-slate-500 block mb-1">Y Axis</label>
          <select value={yKey} onChange={(e) => setYKey(e.target.value)} className={selectClass}>
            {METRICS.map((m) => (
              <option key={m.key} value={m.key}>{m.label}</option>
            ))}
          </select>
        </div>
      </div>

      <div className="bg-slate-900 border border-slate-800 rounded-xl p-5">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-sm font-semibold text-slate-300">
            {METRICS.find((m) => m.key === xKey)?.label} vs{" "}
            {METRICS.find((m) => m.key === yKey)?.label}
          </h2>
          <div className="text-right">
            <p className="text-xs text-slate-500">Pearson r</p>
            <p className={`text-lg font-bold ${rColor}`}>{correlation.toFixed(2)}</p>
            <p className="text-xs text-slate-500">{rStrength}</p>
          </div>
        </div>

        {scatterData.length < 5 ? (
          <p className="text-slate-500 text-sm text-center py-12">
            Not enough data points ({scatterData.length}). Need at least 5.
          </p>
        ) : (
          <ResponsiveContainer width="100%" height={300}>
            <ScatterChart margin={{ top: 5, right: 5, left: -20, bottom: 5 }}>
              <CartesianGrid strokeDasharray="3 3" stroke="#1e293b" />
              <XAxis
                dataKey="x"
                name={METRICS.find((m) => m.key === xKey)?.label}
                tick={{ fill: "#64748b", fontSize: 11 }}
                tickLine={false}
                axisLine={{ stroke: "#1e293b" }}
              />
              <YAxis
                dataKey="y"
                name={METRICS.find((m) => m.key === yKey)?.label}
                tick={{ fill: "#64748b", fontSize: 11 }}
                tickLine={false}
                axisLine={false}
              />
              <Tooltip
                cursor={{ strokeDasharray: "3 3" }}
                contentStyle={{
                  background: "#1e293b",
                  border: "1px solid #334155",
                  borderRadius: "8px",
                  fontSize: "12px",
                }}
              />
              <Scatter data={scatterData} fill="#6366f1" opacity={0.7} />
            </ScatterChart>
          </ResponsiveContainer>
        )}

        {summary && (
          <div className="mt-4 p-3 bg-slate-800/50 rounded-lg">
            <p className="text-sm text-slate-300">{summary}</p>
          </div>
        )}
      </div>
    </div>
  );
}
