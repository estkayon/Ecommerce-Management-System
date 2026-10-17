
import { cookies } from "next/headers";
import { NextResponse } from "next/server";

export const runtime = "nodejs";

export async function POST(request: Request) {
  const origin = new URL(request.url).origin;

  if (request.headers.get("origin") !== origin) {
    return NextResponse.json(
      { message: "Invalid request origin." },
      { status: 403 }
    );
  }

  const cookieStore = await cookies();
  const token = cookieStore.get("shopsphere_admin")?.value;

  if (token) {
    try {
      const apiUrl =
        process.env.LARAVEL_API_URL ??
        "http://127.0.0.1:8000/api";

      await fetch(`${apiUrl}/admin/logout`, {
        method: "POST",
        headers: {
          Accept: "application/json",
          Authorization: `Bearer ${token}`,
        },
        cache: "no-store",
        signal: AbortSignal.timeout(10000),
      });
    } catch {
      // Clear the browser session even if backend is unavailable.
    }
  }

  const response = NextResponse.json({
    message: "Local session cleared.",
  });

  response.cookies.delete("shopsphere_admin");
  response.headers.set("Cache-Control", "no-store");

  return response;
}
