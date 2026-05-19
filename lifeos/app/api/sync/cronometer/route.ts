import { NextRequest } from "next/server";
import { prisma } from "@/lib/prisma";
import { toDateOnly } from "@/lib/utils";
import Papa from "papaparse";

export async function POST(req: NextRequest) {
  try {
    const formData = await req.formData();
    const file = formData.get("file") as File | null;
    if (!file) {
      return Response.json({ ok: false, error: "No file provided" }, { status: 400 });
    }

    const text = await file.text();
    const { data, errors } = Papa.parse<Record<string, string>>(text, {
      header: true,
      skipEmptyLines: true,
    });

    if (errors.length > 0) {
      return Response.json({ ok: false, error: "CSV parse error" }, { status: 400 });
    }

    let upserted = 0;
    for (const row of data) {
      // Cronometer CSV has "Date" column in YYYY-MM-DD format
      const dateStr = row["Date"] ?? row["date"];
      if (!dateStr) continue;

      const date = toDateOnly(new Date(dateStr));
      const caloriesIn = parseFloat(row["Energy (kcal)"] ?? row["Calories"] ?? "0") || null;
      const proteinG = parseFloat(row["Protein (g)"] ?? row["Protein"] ?? "0") || null;
      const carbsG = parseFloat(row["Carbs (g)"] ?? row["Carbohydrates (g)"] ?? row["Net Carbs (g)"] ?? "0") || null;
      const fatG = parseFloat(row["Fat (g)"] ?? row["Fat"] ?? "0") || null;

      const updateData: Record<string, unknown> = {};
      if (caloriesIn) updateData.caloriesIn = Math.round(caloriesIn);
      if (proteinG) updateData.proteinG = proteinG;
      if (carbsG) updateData.carbsG = carbsG;
      if (fatG) updateData.fatG = fatG;

      if (Object.keys(updateData).length > 0) {
        await prisma.dailyLog.upsert({
          where: { date },
          update: updateData,
          create: { date, ...updateData },
        });
        upserted++;
      }
    }

    await prisma.syncLog.create({
      data: { source: "cronometer", status: "success", message: `Upserted ${upserted} days` },
    });

    return Response.json({ ok: true, upserted });
  } catch (err) {
    await prisma.syncLog.create({
      data: { source: "cronometer", status: "error", message: String(err) },
    });
    return Response.json({ ok: false, error: String(err) }, { status: 500 });
  }
}
