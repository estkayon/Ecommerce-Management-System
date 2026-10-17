
import { NextResponse } from "next/server";
import { adminApi } from "@/lib/admin-api";

export const runtime = "nodejs";

type Context = {
  params: Promise<{ id: string }>;
};

export async function GET(
  _request: Request,
  context: Context
) {
  const { id } = await context.params;

  if (!/^[1-9]\d*$/.test(id)) {
    return NextResponse.json(
      { message: "Invalid delivery ID." },
      { status: 422 }
    );
  }

  return adminApi(`/admin/deliveries/${id}`);
}
