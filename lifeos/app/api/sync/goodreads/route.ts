import { NextRequest } from "next/server";
import { prisma } from "@/lib/prisma";
import Parser from "rss-parser";
import { toDateOnly } from "@/lib/utils";

export async function POST(req: NextRequest) {
  try {
    const body = await req.json();
    const rssUrl = body.rssUrl as string;
    if (!rssUrl) {
      return Response.json({ ok: false, error: "rssUrl required" }, { status: 400 });
    }

    const parser = new Parser();
    const feed = await parser.parseURL(rssUrl);

    let count = 0;
    for (const item of feed.items ?? []) {
      if (item.isoDate) {
        const date = toDateOnly(new Date(item.isoDate));
        // Store reading activity as a note addition; extend schema if needed
        await prisma.dailyLog.upsert({
          where: { date },
          update: {},
          create: { date },
        });
        count++;
      }
    }

    await prisma.syncLog.create({
      data: { source: "goodreads", status: "success", message: `Found ${count} entries` },
    });

    return Response.json({ ok: true, count });
  } catch (err) {
    await prisma.syncLog.create({
      data: { source: "goodreads", status: "error", message: String(err) },
    });
    return Response.json({ ok: false, error: String(err) }, { status: 500 });
  }
}
