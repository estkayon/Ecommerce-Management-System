
import { NextResponse } from "next/server";
import {
  adminApi,
  validAdminMutationOrigin,
} from "@/lib/admin-api";

export const runtime = "nodejs";

type Context = {
  params: Promise<{ id: string }>;
};

function validId(id: string) {
  return /^[1-9]\d*$/.test(id);
}

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

  if (!validId(id)) {
    return NextResponse.json(
      { message: "Invalid product ID." },
      { status: 422 }
    );
  }

  try {
    const body = await request.json();

    return adminApi(`/admin/products/${id}`, {
      method: "PATCH",
      body,
    });
  } catch {
    return NextResponse.json(
      { message: "Invalid request body." },
      { status: 400 }
    );
  }
}

export async function DELETE(
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

  if (!validId(id)) {
    return NextResponse.json(
      { message: "Invalid product ID." },
      { status: 422 }
    );
  }

  return adminApi(`/admin/products/${id}`, {
    method: "DELETE",
  });
}
