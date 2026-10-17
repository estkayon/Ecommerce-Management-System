
import { NextResponse } from "next/server";
import {
  adminApi,
  validAdminMutationOrigin,
} from "@/lib/admin-api";

export const runtime = "nodejs";

type Context = {
  params: Promise<{ id: string }>;
};

export async function GET(
  request: Request,
  context: Context
) {
  const { id } = await context.params;

  if (!/^[1-9]\d*$/.test(id)) {
    return NextResponse.json(
      { message: "Invalid product ID." },
      { status: 422 }
    );
  }

  const url = new URL(request.url);
  const requestedPage = Number(
    url.searchParams.get("page") ?? "1"
  );

  const page =
    Number.isSafeInteger(requestedPage) && requestedPage > 0
      ? requestedPage
      : 1;

  return adminApi(
    `/admin/products/${id}/inventory?page=${page}`
  );
}

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
      { message: "Invalid product ID." },
      { status: 422 }
    );
  }

  let body: unknown;

  try {
    body = await request.json();
  } catch {
    return NextResponse.json(
      { message: "Invalid JSON body." },
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
  const quantity = values.quantity_change;
  const reason = values.reason;

  if (
    typeof quantity !== "number" ||
    !Number.isSafeInteger(quantity) ||
    quantity === 0 ||
    Math.abs(quantity) > 1000000 ||
    typeof reason !== "string" ||
    !reason.trim() ||
    reason.length > 255
  ) {
    return NextResponse.json(
      { message: "Invalid stock adjustment." },
      { status: 422 }
    );
  }

  return adminApi(
    `/admin/products/${id}/inventory/adjust`,
    {
      method: "POST",
      body: {
        quantity_change: quantity,
        reason: reason.trim(),
      },
    }
  );
}
