"use client";

import { useState } from "react";
import type { Goal } from "@/app/generated/prisma/client";

function GoalCard({ goal, onUpdate }: { goal: Goal; onUpdate: () => void }) {
  const pct = Math.min(100, goal.target > 0 ? (goal.current / goal.target) * 100 : 0);
  const barColor =
    pct >= 100 ? "bg-emerald-500" : pct >= 60 ? "bg-blue-500" : "bg-amber-500";

  const [editing, setEditing] = useState(false);
  const [current, setCurrent] = useState(String(goal.current));

  const save = async () => {
    await fetch("/api/goals", {
      method: "PATCH",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id: goal.id, current: Number(current) }),
    });
    setEditing(false);
    onUpdate();
  };

  const remove = async () => {
    await fetch("/api/goals", {
      method: "DELETE",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id: goal.id }),
    });
    onUpdate();
  };

  return (
    <div className="bg-slate-900 border border-slate-800 rounded-xl p-5">
      <div className="flex items-start justify-between mb-3">
        <div>
          <h3 className="text-white font-medium">{goal.name}</h3>
          <p className="text-xs text-slate-500">{goal.metric}</p>
        </div>
        <button onClick={remove} className="text-slate-600 hover:text-red-400 text-xs">
          ✕
        </button>
      </div>
      <div className="flex items-baseline gap-1 mb-2">
        {editing ? (
          <div className="flex gap-2">
            <input
              type="number"
              step="any"
              value={current}
              onChange={(e) => setCurrent(e.target.value)}
              className="w-24 bg-slate-800 border border-slate-700 rounded px-2 py-1 text-white text-sm"
            />
            <button onClick={save} className="text-xs text-blue-400 hover:text-blue-300">
              Save
            </button>
          </div>
        ) : (
          <button onClick={() => setEditing(true)} className="text-left">
            <span className="text-xl font-bold text-white">{goal.current}</span>
            <span className="text-slate-400 text-sm ml-1">
              / {goal.target} {goal.unit}
            </span>
          </button>
        )}
      </div>
      <div className="w-full bg-slate-800 rounded-full h-2">
        <div
          className={`h-2 rounded-full transition-all ${barColor}`}
          style={{ width: `${pct}%` }}
        />
      </div>
      <p className="text-xs text-slate-500 mt-1">{pct.toFixed(0)}% complete</p>
      {goal.deadline && (
        <p className="text-xs text-slate-600 mt-1">
          Due {new Date(goal.deadline).toLocaleDateString()}
        </p>
      )}
    </div>
  );
}

export default function GoalsClient({ initialGoals }: { initialGoals: Goal[] }) {
  const [goals, setGoals] = useState(initialGoals);
  const [adding, setAdding] = useState(false);
  const [form, setForm] = useState({
    name: "",
    metric: "",
    target: "",
    current: "",
    unit: "",
    deadline: "",
  });

  const refresh = async () => {
    const res = await fetch("/api/goals");
    setGoals(await res.json());
  };

  const handleAdd = async (e: React.FormEvent) => {
    e.preventDefault();
    await fetch("/api/goals", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        ...form,
        target: Number(form.target),
        current: Number(form.current || 0),
      }),
    });
    setForm({ name: "", metric: "", target: "", current: "", unit: "", deadline: "" });
    setAdding(false);
    refresh();
  };

  const inputClass =
    "w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-blue-500";

  return (
    <div className="space-y-6">
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {goals.map((g) => (
          <GoalCard key={g.id} goal={g} onUpdate={refresh} />
        ))}
        <button
          onClick={() => setAdding(true)}
          className="bg-slate-900 border border-slate-700 border-dashed rounded-xl p-5 text-slate-500 hover:text-white hover:border-slate-600 transition-colors text-sm"
        >
          + Add Goal
        </button>
      </div>

      {adding && (
        <form
          onSubmit={handleAdd}
          className="bg-slate-900 border border-slate-800 rounded-xl p-5 max-w-md space-y-3"
        >
          <h3 className="text-sm font-semibold text-slate-300">New Goal</h3>
          <input
            required
            placeholder="Name (e.g. Weight Loss)"
            value={form.name}
            onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
            className={inputClass}
          />
          <input
            required
            placeholder="Metric (e.g. weight)"
            value={form.metric}
            onChange={(e) => setForm((f) => ({ ...f, metric: e.target.value }))}
            className={inputClass}
          />
          <div className="grid grid-cols-2 gap-2">
            <input
              required
              type="number"
              step="any"
              placeholder="Target"
              value={form.target}
              onChange={(e) => setForm((f) => ({ ...f, target: e.target.value }))}
              className={inputClass}
            />
            <input
              placeholder="Unit (lbs, mi, hrs)"
              value={form.unit}
              onChange={(e) => setForm((f) => ({ ...f, unit: e.target.value }))}
              className={inputClass}
            />
          </div>
          <input
            type="number"
            step="any"
            placeholder="Current value"
            value={form.current}
            onChange={(e) => setForm((f) => ({ ...f, current: e.target.value }))}
            className={inputClass}
          />
          <input
            type="date"
            placeholder="Deadline (optional)"
            value={form.deadline}
            onChange={(e) => setForm((f) => ({ ...f, deadline: e.target.value }))}
            className={inputClass}
          />
          <div className="flex gap-2">
            <button
              type="submit"
              className="flex-1 bg-blue-600 hover:bg-blue-700 text-white text-sm py-2 rounded-lg"
            >
              Add
            </button>
            <button
              type="button"
              onClick={() => setAdding(false)}
              className="flex-1 bg-slate-800 text-slate-400 text-sm py-2 rounded-lg"
            >
              Cancel
            </button>
          </div>
        </form>
      )}
    </div>
  );
}
