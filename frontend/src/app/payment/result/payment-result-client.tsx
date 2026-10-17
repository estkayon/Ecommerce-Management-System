
"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { useSearchParams } from "next/navigation";
import { useCart } from "@/components/cart-provider";

type PaymentStatus = {
  id: number;
  order_number: string;
  status: string;
  payment_status: string;
  delivery_status: string;
  total: string;
  delivery: {
    status: string;
    tracking_number: string | null;
  } | null;
};

type StoredPayment = {
  orderId: number;
  token: string;
};

export default function PaymentResultClient() {  
  const params = useSearchParams();
  const { clearCart } = useCart();

  const [loading, setLoading] = useState(true);
  const [status, setStatus] = useState<PaymentStatus | null>(null);
  const [error, setError] = useState("");

  const orderParam = params.get("order");

  useEffect(() => {
    let cancelled = false;

    async function verifyPayment() {
      try {
        const stored = sessionStorage.getItem(
          "shopsphere-pending-payment"
        );

        if (!stored) {
          throw new Error(
            "Payment verification session not found. Please contact support with your order reference."
          );
        }

        const payment = JSON.parse(stored) as StoredPayment;

        if (
          !Number.isSafeInteger(payment.orderId) ||
          payment.orderId <= 0 ||
          typeof payment.token !== "string" ||
          !/^[A-Za-z0-9]{64}$/.test(payment.token) ||
          orderParam !== String(payment.orderId)
        ) {
          throw new Error(
            "The payment return does not match your checkout session."
          );
        }

        const response = await fetch("/api/payments/status", {
          method: "POST",
          headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
          },
          cache: "no-store",
          body: JSON.stringify({
            order_id: payment.orderId,
            payment_access_token: payment.token,
          }),
        });

        const result: {
          data?: PaymentStatus;
          message?: string;
        } = await response.json();

        if (!response.ok || !result.data) {
          throw new Error(
            result.message ?? "Unable to verify payment."
          );
        }

        if (cancelled) return;

        setStatus(result.data);

        if (result.data.payment_status === "paid") {
          clearCart();

          sessionStorage.removeItem(
            "shopsphere-pending-payment"
          );
        }
      } catch (caught) {
        if (!cancelled) {
          setError(
            caught instanceof Error
              ? caught.message
              : "Payment verification is unavailable."
          );
        }
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    }

    void verifyPayment();

    return () => {
      cancelled = true;
    };
  }, [orderParam, clearCart]);

  const isPaid = status?.payment_status === "paid";
  const isPending = status?.payment_status === "pending";

  const title = loading
    ? "Checking your payment..."
    : isPaid
      ? "Payment Successful!"
      : isPending
        ? "Payment Confirmation Pending"
        : status
          ? "Payment Not Completed"
          : "Unable to Verify Payment";

  const description = loading
    ? "We are checking your order with the store."
    : isPaid
      ? "Your payment has been verified and your order is confirmed."
      : isPending
        ? "Your order is still pending. Do not place a duplicate order while verification continues."
        : status
          ? "Your payment is not confirmed. Please check with the store before trying again."
          : error;

  return (
    <main className="flex min-h-screen items-center justify-center bg-[#f8fafc] px-5 py-12 text-slate-900">
      <div className="w-full max-w-xl">
        <Link
          href="/"
          className="mb-9 block text-center text-2xl font-black tracking-tight"
        >
          Shop<span className="text-indigo-600">Sphere.</span>
        </Link>

        <div className="rounded-[30px] border border-slate-200 bg-white p-8 text-center shadow-xl shadow-slate-200/50 sm:p-12">
          <div
            className={`mx-auto flex h-20 w-20 items-center justify-center rounded-3xl text-4xl font-bold ${
              loading
                ? "bg-indigo-50 text-indigo-600"
                : isPaid
                  ? "bg-emerald-50 text-emerald-600"
                  : isPending
                    ? "bg-amber-50 text-amber-600"
                    : "bg-rose-50 text-rose-600"
            }`}
          >
            {loading ? "◷" : isPaid ? "✓" : isPending ? "…" : "!"}
          </div>

          <p className="mt-9 text-xs font-bold tracking-[0.2em] text-indigo-600 uppercase">
            Payment Status
          </p>

          <h1 className="mt-4 text-3xl font-black tracking-tight sm:text-4xl">
            {title}
          </h1>

          <p className="mt-5 text-sm leading-7 text-slate-500">
            {description}
          </p>

          {status && (
            <div className="mt-8 space-y-4 rounded-2xl bg-slate-50 p-6 text-left text-sm">
              <div className="flex justify-between gap-4">
                <span className="text-slate-500">Order</span>
                <span className="break-all text-right font-bold">
                  {status.order_number}
                </span>
              </div>

              <div className="flex justify-between">
                <span className="text-slate-500">Payment</span>
                <span className="font-bold capitalize">
                  {status.payment_status}
                </span>
              </div>

              <div className="flex justify-between">
                <span className="text-slate-500">Delivery</span>
                <span className="font-bold capitalize">
                  {status.delivery_status}
                </span>
              </div>

              {status.delivery?.tracking_number && (
                <div className="flex justify-between gap-4">
                  <span className="text-slate-500">Tracking</span>
                  <span className="font-bold">
                    {status.delivery.tracking_number}
                  </span>
                </div>
              )}
            </div>
          )}

          <div className="mt-9 grid gap-3 sm:grid-cols-2">
            <Link
              href="/"
              className="rounded-xl bg-indigo-600 px-5 py-4 text-sm font-bold text-white transition hover:bg-indigo-700"
            >
              Back to Store
            </Link>

            <Link
              href="/cart"
              className="rounded-xl border border-slate-200 px-5 py-4 text-sm font-bold text-slate-700 transition hover:bg-slate-50"
            >
              View Cart
            </Link>
          </div>

          {!isPaid && !loading && (
            <p className="mt-7 text-xs leading-6 text-slate-400">
              Payment results are verified by the Laravel backend.
              The cart will be preserved until payment is confirmed.
            </p>
          )}
        </div>
      </div>
    </main>
  );
}
