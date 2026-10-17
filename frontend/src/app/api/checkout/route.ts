
import { NextResponse } from "next/server";

export const runtime = "nodejs";

export async function POST(request: Request) {
  try {
    const body = await request.json();

    const baseUrl =
      process.env.LARAVEL_API_URL ??
      "http://127.0.0.1:8000/api";

    const response = await fetch(`${baseUrl}/checkout`, {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      body: JSON.stringify(body),
      cache: "no-store",
      signal: AbortSignal.timeout(20000),
    });

    const result = await response.json();

    return NextResponse.json(result, {
      status: response.status,
      headers: {
        "Cache-Control": "no-store",
      },
    });
  } catch {
    return NextResponse.json(
      {
        message:
          "Checkout service is currently unavailable. Please try again.",
      },
      { status: 503 }
    );
  }
}
