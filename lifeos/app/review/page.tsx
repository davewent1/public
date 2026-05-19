import { prisma } from "@/lib/prisma";
import WeeklyReviewClient from "./WeeklyReviewClient";

export const dynamic = "force-dynamic";

export default async function ReviewPage() {
  const reviews = await prisma.weeklyReview.findMany({
    orderBy: { weekStart: "desc" },
    take: 4,
  });

  return (
    <div className="space-y-8">
      <h1 className="text-2xl font-bold text-white">Weekly Review</h1>
      <WeeklyReviewClient initialReviews={reviews} />
    </div>
  );
}
