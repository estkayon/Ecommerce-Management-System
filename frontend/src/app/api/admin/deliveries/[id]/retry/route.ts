
import { NextResponse } from "next/server";
import {
  adminApi,
  validAdminMutationOrigin,
} from "@/lib/admin-api";

export const runtime = "nodejs";

type Context = {
  params: Promise<{ id: string }>;
};

export async function POST(
  request: Request,
  context: Context
) {
  if (!validAdminMutationOrigin(request)) {
    return NextResponse.json(
      { message: "Invalid request origin." },
      { status: 403 }
    );
  }

  const { id } = await context.params;

  if (!/^[1-9]\d*$/.test(id)) {
    return NextResponse.json(
      { message: "Invalid delivery ID." },
      { status: 422 }
    );
  }

  return adminApi(`/admin/deliveries/${id}/retry`, {
    method: "POST",
  });
}
