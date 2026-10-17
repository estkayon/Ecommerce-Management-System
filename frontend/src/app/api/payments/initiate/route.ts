
import { NextResponse } from "next/server";

export const runtime = "nodejs";

export async function POST(request: Request) {
  try {
    const body = await request.json();

    const orderId = Number(body.order_id);
    const token = body.payment_access_token;

    if (
      !Number.isSafeInteger(orderId) ||
      orderId <= 0 ||
      typeof token !== "string" ||
      token.length !== 64 ||
      !/^[A-Za-z0-9]+$/.test(token)
    ) {
      return NextResponse.json(
        { message: "Invalid payment request." },
        { status: 422 }
      );
    }

    const baseUrl =
      process.env.LARAVEL_API_URL ??
      "http://127.0.0.1:8000/api";

    const response = await fetch(
      `${baseUrl}/orders/${orderId}/payments/initiate`,
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
        signal: AbortSignal.timeout(30000),
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
      {
        message:
          "Payment initiation could not be confirmed. Do not create another order yet.",
      },
      { status: 503 }
    );
  }
}
