
import { NextResponse } from "next/server";
import {
  adminApi,
  validAdminMutationOrigin,
} from "@/lib/admin-api";

export const runtime = "nodejs";

export async function GET(request: Request) {
  const url = new URL(request.url);
  const params = new URLSearchParams();

  const search = url.searchParams.get("search");
  const requestedPage = Number(url.searchParams.get("page") ?? "1");

  const page =
    Number.isSafeInteger(requestedPage) && requestedPage > 0
      ? requestedPage
      : 1;

  params.set("page", String(page));

  if (search?.trim()) {
    params.set("search", search.trim().slice(0, 100));
  }

  return adminApi(`/admin/products?${params.toString()}`);
}

export async function POST(request: Request) {
  if (!validAdminMutationOrigin(request)) {
    return NextResponse.json(
      { message: "Invalid request origin." },
      { status: 403 }
    );
  }

  try {
    const body = await request.json();

    return adminApi("/admin/products", {
      method: "POST",
      body,
    });
  } catch {
    return NextResponse.json(
      { message: "Invalid request body." },
      { status: 400 }
    );
  }
}
