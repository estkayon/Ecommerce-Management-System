
import { cookies } from "next/headers";
import { NextResponse } from "next/server";

const API_URL =
  process.env.LARAVEL_API_URL ??
  "http://127.0.0.1:8000/api";

export async function adminApi(
  path: string,
  options: {
    method?: "GET" | "POST" | "PATCH" | "DELETE";
    body?: unknown;
  } = {}
) {
  const token = (await cookies()).get("shopsphere_admin")?.value;

  if (!token) {
    return NextResponse.json(
      { message: "Please sign in again." },
      { status: 401 }
    );
  }

  try {
    const response = await fetch(`${API_URL}${path}`, {
      method: options.method ?? "GET",
      headers: {
        Accept: "application/json",
        Authorization: `Bearer ${token}`,
        ...(options.body !== undefined
          ? { "Content-Type": "application/json" }
          : {}),
      },
      ...(options.body !== undefined
        ? { body: JSON.stringify(options.body) }
        : {}),
      cache: "no-store",
      signal: AbortSignal.timeout(15000),
    });

    const text = await response.text();
    let result: unknown;

    try {
      result = text ? JSON.parse(text) : {};
    } catch {
      return NextResponse.json(
        { message: "Invalid backend response." },
        { status: 502 }
      );
    }

    return NextResponse.json(result, {
      status: response.status,
      headers: { "Cache-Control": "no-store" },
    });
  } catch {
    return NextResponse.json(
      { message: "Backend connection unavailable." },
      { status: 503 }
    );
  }
}

export function validAdminMutationOrigin(
  request: Request
): boolean {
  const origin = request.headers.get("origin");

  return origin === new URL(request.url).origin;
}
