
import { Suspense } from "react";
import PaymentResultClient from "./payment-result-client";

export default function PaymentResultPage() {
  return (
    <Suspense
      fallback={
        <main className="flex min-h-screen items-center justify-center bg-[#f8fafc] px-5 text-slate-900">
          <div className="w-full max-w-md rounded-3xl border border-slate-200 bg-white p-10 text-center shadow-lg">
            <div className="mx-auto h-12 w-12 animate-spin rounded-full border-4 border-indigo-100 border-t-indigo-600" />

            <h1 className="mt-7 text-xl font-extrabold">
              Verifying Your Payment
            </h1>

            <p className="mt-3 text-sm leading-6 text-slate-500">
              Please wait while we check your payment
              status with our secure server.
            </p>
          </div>
        </main>
      }
    >
      <PaymentResultClient />
    </Suspense>
  );
}
