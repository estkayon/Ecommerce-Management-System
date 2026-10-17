
import { NextResponse } from "next/server";
import {
  adminApi,
  validAdminMutationOrigin,
} from "@/lib/admin-api";

export const runtime = "nodejs";

type Context = {
  params: Promise<{ id: string }>;
};

export async function PATCH(
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
      { message: "Invalid payment ID." },
      { status: 422 }
    );
  }

  let body: unknown;

  try {
    body = await request.json();
  } catch {
    return NextResponse.json(
      { message: "Invalid JSON." },
      { status: 400 }
    );
  }

  if (!body || typeof body !== "object" || Array.isArray(body)) {
    return NextResponse.json(
      { message: "Invalid request body." },
      { status: 422 }
    );
  }

  const values = body as Record<string, unknown>;
  const decision = values.decision;
  const note = values.note;

  if (
    !["investigating", "refund_required"].includes(
      String(decision)
    ) ||
    typeof note !== "string" ||
    note.trim().length < 10 ||
    note.trim().length > 1000
  ) {
    return NextResponse.json(
      {
        message:
          "A valid decision and note (10–1000 characters) are required.",
      },
      { status: 422 }
    );
  }

  return adminApi(`/admin/payment-reviews/${id}`, {
    method: "PATCH",
    body: {
      decision,
      note: note.trim(),
    },
  });
}
