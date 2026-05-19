import { NextRequest } from "next/server";
import { prisma } from "@/lib/prisma";

export async function GET() {
  const goals = await prisma.goal.findMany({
    where: { active: true },
    orderBy: { createdAt: "asc" },
  });
  return Response.json(goals);
}

export async function POST(req: NextRequest) {
  try {
    const body = await req.json();
    const goal = await prisma.goal.create({
      data: {
        name: body.name,
        metric: body.metric,
        target: Number(body.target),
        current: Number(body.current ?? 0),
        unit: body.unit,
        deadline: body.deadline ? new Date(body.deadline) : null,
      },
    });
    return Response.json({ ok: true, goal });
  } catch (err) {
    return Response.json({ ok: false, error: String(err) }, { status: 500 });
  }
}

export async function PATCH(req: NextRequest) {
  try {
    const body = await req.json();
    const { id, ...data } = body;
    if (data.deadline) data.deadline = new Date(data.deadline);
    const goal = await prisma.goal.update({ where: { id }, data });
    return Response.json({ ok: true, goal });
  } catch (err) {
    return Response.json({ ok: false, error: String(err) }, { status: 500 });
  }
}

export async function DELETE(req: NextRequest) {
  try {
    const { id } = await req.json();
    await prisma.goal.update({ where: { id }, data: { active: false } });
    return Response.json({ ok: true });
  } catch (err) {
    return Response.json({ ok: false, error: String(err) }, { status: 500 });
  }
}
