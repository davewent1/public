import SyncClient from "./SyncClient";

export const dynamic = "force-dynamic";

export default function SyncPage() {
  return (
    <div className="space-y-8">
      <h1 className="text-2xl font-bold text-white">Data Sync</h1>
      <SyncClient />
    </div>
  );
}
