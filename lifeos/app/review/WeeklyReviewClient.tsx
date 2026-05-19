"use client";

import { useState } from "react";
import type { WeeklyReview } from "@/app/generated/prisma/client";

export default function WeeklyReviewClient({
  initialReviews,
}: {
  initialReviews: WeeklyReview[];
}) {
  const [reviews, setReviews] = useState(initialReviews);
  const [generating, setGenerating] = useState(false);
  const [error, setError] = useState("");

  const generate = async () => {
    setGenerating(true);
    setError("");
    const res = await fetch("/api/review/weekly", { method: "POST" });
    const json = await res.json();
    if (!json.ok) {
      setError(json.error ?? "Failed to generate");
    } else {
      const res2 = await fetch("/api/review/weekly");
      setReviews(await res2.json());
    }
    setGenerating(false);
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-4">
        <button
          onClick={generate}
          disabled={generating}
          className="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors"
        >
          {generating ? "Generating..." : "Generate This Week's Review"}
        </button>
        {error && <p className="text-red-400 text-sm">{error}</p>}
      </div>

      {reviews.length === 0 ? (
        <p className="text-slate-500 text-sm">No reviews yet. Click generate to create your first.</p>
      ) : (
        <div className="space-y-4">
          {reviews.map((r) => (
            <div key={r.id} className="bg-slate-900 border border-slate-800 rounded-xl p-6">
              <p className="text-xs text-slate-500 mb-3">
                Week of{" "}
                {new Date(r.weekStart).toLocaleDateString("en-US", {
                  month: "long",
                  day: "numeric",
                  year: "numeric",
                })}
              </p>
              <p className="text-slate-200 text-sm leading-relaxed whitespace-pre-wrap">
                {r.content}
              </p>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
