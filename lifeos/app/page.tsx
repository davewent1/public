import { prisma } from "@/lib/prisma";
import { todayUTC, formatDate, statusColor } from "@/lib/utils";
import MetricCard from "@/components/MetricCard";
import ManualInputForm from "@/components/ManualInputForm";
import FocusPrompt from "@/components/FocusPrompt";

export const dynamic = "force-dynamic";

async function getTodayLog() {
  const today = todayUTC();
  return prisma.dailyLog.findFirst({ where: { date: today } });
}

async function getLastSync() {
  return prisma.syncLog.findFirst({ orderBy: { syncedAt: "desc" } });
}

export default async function DashboardPage() {
  const [log, lastSync] = await Promise.all([getTodayLog(), getLastSync()]);

  return (
    <div className="space-y-8">
      <div className="flex items-start justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white">{formatDate(new Date())}</h1>
          <p className="text-slate-500 text-sm mt-1">
            {lastSync
              ? `Last synced ${new Date(lastSync.syncedAt).toLocaleTimeString()} via ${lastSync.source}`
              : "Not yet synced"}
          </p>
        </div>
      </div>

      {log && <FocusPrompt log={log} />}

      {log ? (
        <div>
          <h2 className="text-xs text-slate-500 uppercase tracking-wider mb-3">Today at a Glance</h2>
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
            <MetricCard
              label="Sleep Score"
              value={log.sleepScore != null ? Math.round(log.sleepScore) : null}
              unit="/100"
              status={statusColor(log.sleepScore, 60, 80)}
            />
            <MetricCard
              label="Sleep"
              value={log.sleepHours != null ? log.sleepHours.toFixed(1) : null}
              unit="hrs"
              status={statusColor(log.sleepHours, 6.5, 8)}
            />
            <MetricCard
              label="HRV"
              value={log.hrv != null ? Math.round(log.hrv) : null}
              unit="ms"
              status={statusColor(log.hrv, 30, 55)}
            />
            <MetricCard
              label="Resting HR"
              value={log.restingHR != null ? Math.round(log.restingHR) : null}
              unit="bpm"
              status={statusColor(log.restingHR, 50, 65, false)}
            />
            <MetricCard
              label="Steps"
              value={log.steps != null ? log.steps.toLocaleString() : null}
              status={statusColor(log.steps, 7500, 10000)}
            />
            <MetricCard
              label="Stress"
              value={log.stressScore != null ? Math.round(log.stressScore) : null}
              unit="/100"
              status={statusColor(log.stressScore, 30, 60, false)}
            />
            <MetricCard
              label="Calories In"
              value={log.caloriesIn}
              unit="kcal"
              status={statusColor(log.caloriesIn, 1800, 2400)}
            />
            <MetricCard
              label="Active Cal"
              value={log.activeCalories}
              unit="kcal"
              status={statusColor(log.activeCalories, 300, 600)}
            />
            <MetricCard
              label="Weight"
              value={log.weight != null ? log.weight.toFixed(1) : null}
              unit="lbs"
            />
            <MetricCard
              label="Mood"
              value={log.mood}
              unit="/10"
              status={statusColor(log.mood, 5, 7)}
            />
            <MetricCard
              label="Energy"
              value={log.energy}
              unit="/10"
              status={statusColor(log.energy, 5, 7)}
            />
            {log.fastingHours != null && (
              <MetricCard
                label="Fasting"
                value={log.fastingHours.toFixed(1)}
                unit="hrs"
                status={statusColor(log.fastingHours, 12, 16)}
              />
            )}
          </div>
        </div>
      ) : (
        <div className="text-center py-12 text-slate-500">
          <p className="text-lg">No data for today yet.</p>
          <p className="text-sm mt-1">Sync Garmin data or log manually below.</p>
        </div>
      )}

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <ManualInputForm />
        <div className="space-y-3">
          {log?.proteinG != null && (
            <div className="bg-slate-900 border border-slate-800 rounded-xl p-4">
              <h3 className="text-xs text-slate-500 uppercase tracking-wider mb-3">Macros</h3>
              <div className="space-y-2">
                {[
                  { label: "Protein", value: log.proteinG, color: "bg-blue-500" },
                  { label: "Carbs", value: log.carbsG, color: "bg-amber-500" },
                  { label: "Fat", value: log.fatG, color: "bg-orange-500" },
                ].map(({ label, value, color }) =>
                  value != null ? (
                    <div key={label} className="flex items-center gap-2">
                      <div className={`w-2 h-2 rounded-full ${color}`} />
                      <span className="text-xs text-slate-400 w-16">{label}</span>
                      <span className="text-sm text-white">{Math.round(value)}g</span>
                    </div>
                  ) : null
                )}
              </div>
            </div>
          )}
          {log?.notes && (
            <div className="bg-slate-900 border border-slate-800 rounded-xl p-4">
              <h3 className="text-xs text-slate-500 uppercase tracking-wider mb-2">Notes</h3>
              <p className="text-sm text-slate-300">{log.notes}</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
