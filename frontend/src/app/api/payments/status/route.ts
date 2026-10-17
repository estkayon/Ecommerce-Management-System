
import { NextResponse } from "next/server";

export const runtime = "nodejs";

export async function POST(request: Request) {
  try {
    const body: unknown = await request.json();

    if (!body || typeof body !== "object") {
      return NextResponse.json(
        { message: "Invalid request." },
        { status: 422 }
      );
    }

    const data = body as Record<string, unknown>;
    const orderId = data.order_id;
    const token = data.payment_access_token;

    if (
      typeof orderId !== "number" ||
      !Number.isSafeInteger(orderId) ||
      orderId <= 0 ||
      typeof token !== "string" ||
      !/^[A-Za-z0-9]{64}$/.test(token)
    ) {
      return NextResponse.json(
        { message: "Invalid payment status request." },
        { status: 422 }
      );
    }

    const baseUrl =
      process.env.LARAVEL_API_URL ??
      "http://127.0.0.1:8000/api";

    const response = await fetch(
      `${baseUrl}/orders/${orderId}/payment-status`,
      {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          payment_access_token: token,
        }),
        cache: "no-store",
        signal: AbortSignal.timeout(15000),
      }
    );

    const result = await response.json();

    return NextResponse.json(result, {
      status: response.status,
      headers: {
        "Cache-Control": "no-store",
      },
    });
  } catch {
    return NextResponse.json(
      { message: "Payment status is currently unavailable." },
      { status: 503 }
    );
  }
}
