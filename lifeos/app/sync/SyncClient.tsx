"use client";

import { useState, useEffect } from "react";

interface SyncLogEntry {
  id: number;
  source: string;
  status: string;
  message: string | null;
  syncedAt: string;
}

export default function SyncClient() {
  const [logs, setLogs] = useState<SyncLogEntry[]>([]);
  const [syncing, setSyncing] = useState<string | null>(null);
  const [csvFile, setCsvFile] = useState<File | null>(null);
  const [rssUrl, setRssUrl] = useState("");

  const refreshLogs = async () => {
    const res = await fetch("/api/sync/status");
    setLogs(await res.json());
  };

  useEffect(() => { refreshLogs(); }, []);

  const syncGarmin = async () => {
    setSyncing("garmin");
    await fetch("/api/sync/garmin", { method: "POST" });
    setSyncing(null);
    refreshLogs();
  };

  const syncCronometer = async () => {
    if (!csvFile) return;
    setSyncing("cronometer");
    const fd = new FormData();
    fd.append("file", csvFile);
    await fetch("/api/sync/cronometer", { method: "POST", body: fd });
    setSyncing(null);
    setCsvFile(null);
    refreshLogs();
  };

  const syncGoodreads = async () => {
    if (!rssUrl) return;
    setSyncing("goodreads");
    await fetch("/api/sync/goodreads", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ rssUrl }),
    });
    setSyncing(null);
    refreshLogs();
  };

  return (
    <div className="space-y-6">
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 space-y-3">
          <h3 className="text-sm font-semibold text-slate-300">Garmin Connect</h3>
          <p className="text-xs text-slate-500">Pulls last 7 days of sleep, HRV, steps, stress.</p>
          <p className="text-xs text-slate-500">
            Set GARMIN_EMAIL and GARMIN_PASSWORD in .env
          </p>
          <button
            onClick={syncGarmin}
            disabled={syncing === "garmin"}
            className="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-sm py-2 rounded-lg"
          >
            {syncing === "garmin" ? "Syncing..." : "Sync Garmin"}
          </button>
        </div>

        <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 space-y-3">
          <h3 className="text-sm font-semibold text-slate-300">Cronometer CSV</h3>
          <p className="text-xs text-slate-500">
            Export from Cronometer → Diary → Export. Upload the CSV here.
          </p>
          <input
            type="file"
            accept=".csv"
            onChange={(e) => setCsvFile(e.target.files?.[0] ?? null)}
            className="text-xs text-slate-400 w-full"
          />
          <button
            onClick={syncCronometer}
            disabled={!csvFile || syncing === "cronometer"}
            className="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-sm py-2 rounded-lg"
          >
            {syncing === "cronometer" ? "Uploading..." : "Upload CSV"}
          </button>
        </div>

        <div className="bg-slate-900 border border-slate-800 rounded-xl p-5 space-y-3">
          <h3 className="text-sm font-semibold text-slate-300">Goodreads RSS</h3>
          <p className="text-xs text-slate-500">
            Paste your Goodreads "read" shelf RSS URL.
          </p>
          <input
            type="url"
            placeholder="https://www.goodreads.com/review/list_rss/..."
            value={rssUrl}
            onChange={(e) => setRssUrl(e.target.value)}
            className="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white text-xs focus:outline-none focus:border-blue-500"
          />
          <button
            onClick={syncGoodreads}
            disabled={!rssUrl || syncing === "goodreads"}
            className="w-full bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-sm py-2 rounded-lg"
          >
            {syncing === "goodreads" ? "Syncing..." : "Sync Goodreads"}
          </button>
        </div>
      </div>

      <div className="bg-slate-900 border border-slate-800 rounded-xl p-5">
        <h3 className="text-sm font-semibold text-slate-300 mb-3">Sync History</h3>
        {logs.length === 0 ? (
          <p className="text-slate-500 text-sm">No syncs yet.</p>
        ) : (
          <div className="space-y-2">
            {logs.map((log) => (
              <div key={log.id} className="flex items-start gap-3 text-xs">
                <span
                  className={`mt-0.5 w-2 h-2 rounded-full flex-shrink-0 ${
                    log.status === "success" ? "bg-emerald-400" : "bg-red-400"
                  }`}
                />
                <div>
                  <span className="text-slate-300 font-medium">{log.source}</span>
                  <span className="text-slate-500 mx-2">
                    {new Date(log.syncedAt).toLocaleString()}
                  </span>
                  {log.message && <span className="text-slate-400">{log.message}</span>}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
