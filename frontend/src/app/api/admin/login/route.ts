
import { NextResponse } from "next/server";

export const runtime = "nodejs";

export async function POST(request: Request) {
  try {
    const body: unknown = await request.json();

    if (!body || typeof body !== "object") {
      return NextResponse.json(
        { message: "Invalid login request." },
        { status: 422 }
      );
    }

    const { email, password } = body as Record<string, unknown>;

    if (typeof email !== "string" || typeof password !== "string") {
      return NextResponse.json(
        { message: "Email and password are required." },
        { status: 422 }
      );
    }

    const apiUrl =
      process.env.LARAVEL_API_URL ??
      "http://127.0.0.1:8000/api";

    const response = await fetch(`${apiUrl}/admin/login`, {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ email, password }),
      cache: "no-store",
      signal: AbortSignal.timeout(15000),
    });

    const data = await response.json();

    if (!response.ok) {
      return NextResponse.json(
        { message: data.message ?? "Login failed." },
        { status: response.status }
      );
    }

    if (typeof data.token !== "string" || !data.token) {
      return NextResponse.json(
        { message: "Invalid authentication response." },
        { status: 502 }
      );
    }

    const result = NextResponse.json({
      message: "Login successful.",
      user: data.user,
    });

    result.cookies.set("shopsphere_admin", data.token, {
      httpOnly: true,
      secure: process.env.NODE_ENV === "production",
      sameSite: "lax",
      path: "/",
      maxAge: 60 * 60 * 8,
    });

    result.headers.set("Cache-Control", "no-store");

    return result;
  } catch {
    return NextResponse.json(
      { message: "Unable to connect to the server." },
      { status: 503 }
    );
  }
}
