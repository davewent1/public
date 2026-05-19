"use client";

import { useState } from "react";
import TrendChart from "@/components/TrendChart";

const METRIC_GROUPS = [
  {
    label: "Sleep",
    metrics: [
      { key: "sleepScore", label: "Sleep Score", color: "#6366f1", goalLow: 60, goalHigh: 80 },
      { key: "sleepHours", label: "Hours", color: "#818cf8" },
    ],
  },
  {
    label: "Recovery",
    metrics: [
      { key: "hrv", label: "HRV (ms)", color: "#10b981", goalLow: 30, goalHigh: 55 },
      { key: "restingHR", label: "Resting HR", color: "#f43f5e" },
    ],
  },
  {
    label: "Activity",
    metrics: [
      { key: "steps", label: "Steps (k)", color: "#f59e0b", goalLow: 7.5, goalHigh: 10 },
      { key: "stressScore", label: "Stress", color: "#ef4444" },
    ],
  },
  {
    label: "Nutrition",
    metrics: [
      { key: "caloriesIn", label: "Calories", color: "#f97316", goalLow: 1800, goalHigh: 2400 },
    ],
  },
  {
    label: "Subjective",
    metrics: [
      { key: "mood", label: "Mood", color: "#a78bfa", goalLow: 5, goalHigh: 8 },
      { key: "energy", label: "Energy", color: "#34d399" },
    ],
  },
  {
    label: "Weight",
    metrics: [{ key: "weight", label: "Weight (lbs)", color: "#60a5fa" }],
  },
];

const WINDOWS = [7, 30, 90];

export default function TrendChartWrapper({ data }: { data: Record<string, any>[] }) {
  const [activeGroup, setActiveGroup] = useState(0);
  const [days, setDays] = useState(30);

  const sliced = data.slice(-days);

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap gap-2">
        {WINDOWS.map((w) => (
          <button
            key={w}
            onClick={() => setDays(w)}
            className={`text-xs px-3 py-1.5 rounded-lg transition-colors ${
              days === w
                ? "bg-blue-600 text-white"
                : "bg-slate-800 text-slate-400 hover:text-white"
            }`}
          >
            {w}d
          </button>
        ))}
      </div>

      <div className="flex flex-wrap gap-2">
        {METRIC_GROUPS.map((g, i) => (
          <button
            key={g.label}
            onClick={() => setActiveGroup(i)}
            className={`text-xs px-3 py-1.5 rounded-lg transition-colors ${
              activeGroup === i
                ? "bg-slate-600 text-white"
                : "bg-slate-800 text-slate-400 hover:text-white"
            }`}
          >
            {g.label}
          </button>
        ))}
      </div>

      <div className="bg-slate-900 border border-slate-800 rounded-xl p-5">
        <h2 className="text-sm font-semibold text-slate-300 mb-4">
          {METRIC_GROUPS[activeGroup].label} — Last {days} days
        </h2>
        {sliced.length === 0 ? (
          <p className="text-slate-500 text-sm text-center py-12">
            No data yet. Sync or log manually.
          </p>
        ) : (
          <TrendChart data={sliced} metrics={METRIC_GROUPS[activeGroup].metrics} />
        )}
      </div>
    </div>
  );
}
