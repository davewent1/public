"use client";
import { useState } from "react";

export default function ManualInputForm() {
  const [form, setForm] = useState({
    mood: "",
    energy: "",
    fastingHours: "",
    weight: "",
    notes: "",
  });
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
    setForm((f) => ({ ...f, [e.target.name]: e.target.value }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    const payload: Record<string, unknown> = {};
    if (form.mood) payload.mood = Number(form.mood);
    if (form.energy) payload.energy = Number(form.energy);
    if (form.fastingHours) payload.fastingHours = Number(form.fastingHours);
    if (form.weight) payload.weight = Number(form.weight);
    if (form.notes) payload.notes = form.notes;

    await fetch("/api/log/manual", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    });
    setSaving(false);
    setSaved(true);
    setTimeout(() => setSaved(false), 3000);
  };

  const inputClass =
    "w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-blue-500";

  return (
    <form onSubmit={handleSubmit} className="bg-slate-900 border border-slate-800 rounded-xl p-5">
      <h3 className="text-sm font-semibold text-slate-300 mb-4">Log Today</h3>
      <div className="grid grid-cols-2 gap-3 mb-3">
        <div>
          <label className="text-xs text-slate-500 mb-1 block">Mood (1-10)</label>
          <input
            name="mood"
            type="number"
            min="1"
            max="10"
            value={form.mood}
            onChange={handleChange}
            placeholder="7"
            className={inputClass}
          />
        </div>
        <div>
          <label className="text-xs text-slate-500 mb-1 block">Energy (1-10)</label>
          <input
            name="energy"
            type="number"
            min="1"
            max="10"
            value={form.energy}
            onChange={handleChange}
            placeholder="7"
            className={inputClass}
          />
        </div>
        <div>
          <label className="text-xs text-slate-500 mb-1 block">Fasting Hours</label>
          <input
            name="fastingHours"
            type="number"
            min="0"
            max="24"
            step="0.5"
            value={form.fastingHours}
            onChange={handleChange}
            placeholder="16"
            className={inputClass}
          />
        </div>
        <div>
          <label className="text-xs text-slate-500 mb-1 block">Weight (lbs)</label>
          <input
            name="weight"
            type="number"
            step="0.1"
            value={form.weight}
            onChange={handleChange}
            placeholder="180"
            className={inputClass}
          />
        </div>
      </div>
      <div className="mb-3">
        <label className="text-xs text-slate-500 mb-1 block">Notes</label>
        <textarea
          name="notes"
          value={form.notes}
          onChange={handleChange}
          placeholder="How are you feeling today?"
          rows={2}
          className={`${inputClass} resize-none`}
        />
      </div>
      <button
        type="submit"
        disabled={saving}
        className="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-sm font-medium py-2 rounded-lg transition-colors"
      >
        {saved ? "Saved!" : saving ? "Saving..." : "Save"}
      </button>
    </form>
  );
}
