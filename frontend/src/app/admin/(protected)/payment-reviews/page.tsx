
"use client";

import { useEffect, useState, type FormEvent } from "react";

type Review = {
  payment_id: number;
  order_id: number;
  order_number: string | null;
  customer_name: string | null;
  transaction_id: string;
  amount: string | number;
  currency: string;
  payment_status: string;
  order_status: string | null;
  review_reason: string | null;
  review_detected_at: string | null;
  last_checked_at: string | null;
  expires_at: string | null;
};

type ReviewResponse = {
  data: Review[];
  meta: {
    current_page: number;
    last_page: number;
    total: number;
    per_page: number;
  };
};

type Decision = "investigating" | "refund_required";

function readableDate(value: string | null) {
  if (!value) return "—";
  const date = new Date(value);
  return Number.isNaN(date.getTime())
    ? value
    : date.toLocaleString("en-BD");
}

async function parseResponse(response: Response) {
  const result = await response.json();

  if (!response.ok) {
    const errors = result.errors as Record<string, string[]> | undefined;
    throw new Error(
      (errors && Object.values(errors).flat()[0]) ||
      result.message ||
      "Request failed."
    );
  }

  return result;
}

export default function PaymentReviewsPage() {
  const [records, setRecords] = useState<ReviewResponse | null>(null);
  const [page, setPage] = useState(1);
  const [refresh, setRefresh] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");

  const [selected, setSelected] = useState<Review | null>(null);
  const [decision, setDecision] = useState<Decision>("investigating");
  const [note, setNote] = useState("");
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState("");

  useEffect(() => {
    const controller = new AbortController();

    async function load() {
      setLoading(true);
      setError("");

      try {
        const result = await parseResponse(
          await fetch(`/api/admin/payment-reviews?page=${page}`, {
            cache: "no-store",
            signal: controller.signal,
          })
        ) as ReviewResponse;

        if (!controller.signal.aborted) setRecords(result);
      } catch (caught) {
        if (!controller.signal.aborted) {
          setError(
            caught instanceof Error ? caught.message : "Unable to load reviews."
          );
        }
      } finally {
        if (!controller.signal.aborted) setLoading(false);
      }
    }

    void load();
    return () => controller.abort();
  }, [page, refresh]);

  function openReview(review: Review) {
    setSelected(review);
    setDecision("investigating");
    setNote("");
    setFormError("");
    setNotice("");
  }

  async function saveDecision(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (!selected || saving) return;

    if (note.trim().length < 10 || note.trim().length > 1000) {
      setFormError("Review note must contain 10–1000 characters.");
      return;
    }

    const confirmed = window.confirm(
      decision === "refund_required"
        ? "Mark this transaction as requiring a refund review? This does NOT execute a refund."
        : "Save the investigation decision for this payment?"
    );

    if (!confirmed) return;

    setSaving(true);
    setFormError("");

    try {
      await parseResponse(
        await fetch(
          `/api/admin/payment-reviews/${selected.payment_id}`,
          {
            method: "PATCH",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
              decision,
              note: note.trim(),
            }),
          }
        )
      );

      setNotice(
        decision === "refund_required"
          ? "Refund-required review recorded. No refund was executed."
          : "Investigation decision saved."
      );

      setSelected(null);
      setRefresh((value) => value + 1);
    } catch (caught) {
      setFormError(
        caught instanceof Error ? caught.message : "Unable to save decision."
      );
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="space-y-7">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-xs font-bold uppercase tracking-[0.18em] text-indigo-600">
            Financial Operations
          </p>
          <h2 className="mt-2 text-3xl font-black tracking-tight">
            Payment Review
          </h2>
          <p className="mt-3 text-sm text-slate-500">
            Investigate late successful payments and record manual review decisions.
          </p>
        </div>

        <button
          onClick={() => setRefresh((value) => value + 1)}
          className="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold hover:bg-slate-50"
        >
          ↻ Refresh
        </button>
      </div>

      <div className="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-900">
        This page only lists transactions flagged as
        <strong> late_successful_payment</strong> by backend reconciliation.
        A <strong>refund_required</strong> decision is a review record,
        not an actual customer refund.
      </div>

      {notice && (
        <p role="status" className="rounded-xl bg-emerald-50 p-4 text-sm font-semibold text-emerald-700">
          {notice}
        </p>
      )}

      {error && (
        <p role="alert" className="rounded-xl bg-rose-50 p-4 text-sm text-rose-700">
          {error}
        </p>
      )}

      <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div className="border-b border-slate-100 p-6">
          <h3 className="font-extrabold">
            Transactions Requiring Review
          </h3>
          <p className="mt-2 text-xs text-slate-500">
            {records?.meta.total ?? "—"} flagged payments
          </p>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full min-w-[850px] text-left text-sm">
            <thead className="bg-slate-50 text-xs font-bold uppercase text-slate-500">
              <tr>
                <th className="px-5 py-4">Payment</th>
                <th className="px-5 py-4">Order / Customer</th>
                <th className="px-5 py-4">Amount</th>
                <th className="px-5 py-4">Status</th>
                <th className="px-5 py-4">Last Checked</th>
                <th className="px-5 py-4 text-right">Action</th>
              </tr>
            </thead>

            <tbody className="divide-y divide-slate-100">
              {loading ? (
                <tr>
                  <td colSpan={6} className="px-5 py-14 text-center text-slate-500">
                    Loading payment reviews...
                  </td>
                </tr>
              ) : !records?.data.length ? (
                <tr>
                  <td colSpan={6} className="px-5 py-14 text-center text-slate-500">
                    No flagged payments currently require review.
                  </td>
                </tr>
              ) : records.data.map((item) => (
                <tr key={item.payment_id} className="hover:bg-slate-50">
                  <td className="px-5 py-5">
                    <p className="font-bold">#{item.payment_id}</p>
                    <p className="mt-1 max-w-48 break-all font-mono text-xs text-slate-500">
                      {item.transaction_id}
                    </p>
                  </td>

                  <td className="px-5 py-5">
                    <p className="font-bold">{item.order_number ?? `#${item.order_id}`}</p>
                    <p className="mt-1 text-xs text-slate-500">{item.customer_name ?? "—"}</p>
                  </td>

                  <td className="px-5 py-5 font-bold">
                    {item.currency} {item.amount}
                  </td>

                  <td className="px-5 py-5">
                    <span className="rounded-full bg-amber-50 px-3 py-1.5 text-xs font-bold capitalize text-amber-700">
                      {item.payment_status}
                    </span>
                  </td>

                  <td className="px-5 py-5 text-xs text-slate-500">
                    {readableDate(item.last_checked_at)}
                  </td>

                  <td className="px-5 py-5 text-right">
                    <button
                      onClick={() => openReview(item)}
                      className="font-bold text-indigo-600 hover:text-indigo-800"
                    >
                      Review →
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {records && records.meta.last_page > 1 && (
          <div className="flex items-center justify-between border-t border-slate-100 p-5">
            <p className="text-xs text-slate-500">
              Page {records.meta.current_page} of {records.meta.last_page}
            </p>
            <div className="flex gap-2">
              <button
                disabled={loading || page <= 1}
                onClick={() => setPage((value) => value - 1)}
                className="rounded-lg border px-4 py-2 text-xs font-bold disabled:opacity-40"
              >
                Previous
              </button>
              <button
                disabled={loading || page >= records.meta.last_page}
                onClick={() => setPage((value) => value + 1)}
                className="rounded-lg border px-4 py-2 text-xs font-bold disabled:opacity-40"
              >
                Next
              </button>
            </div>
          </div>
        )}
      </section>

      {selected && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm">
          <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="review-title"
            className="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-3xl bg-white p-7 shadow-2xl sm:p-9"
          >
            <div className="flex items-center justify-between gap-4">
              <h3 id="review-title" className="text-2xl font-black">
                Review Payment #{selected.payment_id}
              </h3>

              <button
                disabled={saving}
                onClick={() => setSelected(null)}
                className="rounded-xl bg-slate-100 px-3 py-2 font-bold disabled:opacity-50"
              >
                ✕
              </button>
            </div>

            <div className="mt-6 space-y-3 rounded-2xl bg-slate-50 p-5 text-sm">
              <p>
                <span className="text-slate-500">Order: </span>
                <strong>{selected.order_number ?? selected.order_id}</strong>
              </p>
              <p>
                <span className="text-slate-500">Reason: </span>
                <strong>{selected.review_reason ?? "—"}</strong>
              </p>
              <p>
                <span className="text-slate-500">Detected: </span>
                <strong>{readableDate(selected.review_detected_at)}</strong>
              </p>
              <p>
                <span className="text-slate-500">Order status: </span>
                <strong>{selected.order_status ?? "—"}</strong>
              </p>
              <p>
                <span className="text-slate-500">Payment status: </span>
                <strong>{selected.payment_status}</strong>
              </p>
            </div>

            <form onSubmit={(event) => void saveDecision(event)} className="mt-7 space-y-5">
              <div>
                <label htmlFor="review-decision" className="mb-2 block text-sm font-bold">
                  Review Decision
                </label>
                <select
                  id="review-decision"
                  value={decision}
                  onChange={(event) =>
                    setDecision(event.target.value as Decision)
                  }
                  className="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm"
                >
                  <option value="investigating">Investigating</option>
                  <option value="refund_required">Refund Required</option>
                </select>
              </div>

              <div>
                <label htmlFor="review-note" className="mb-2 block text-sm font-bold">
                  Review Note
                </label>

                <textarea
                  id="review-note"
                  required
                  minLength={10}
                  maxLength={1000}
                  rows={4}
                  value={note}
                  onChange={(event) => setNote(event.target.value)}
                  placeholder="Explain why this transaction requires investigation or refund review..."
                  className="w-full resize-none rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-indigo-500"
                />
                <p className="mt-2 text-right text-xs text-slate-400">
                  {note.length}/1000
                </p>
              </div>

              {decision === "refund_required" && (
                <p className="rounded-xl bg-amber-50 p-4 text-xs leading-6 text-amber-800">
                  This only records a refund-required decision.
                  It does not send money back to the customer.
                </p>
              )}

              {formError && (
                <p role="alert" className="rounded-xl bg-rose-50 p-4 text-sm text-rose-700">
                  {formError}
                </p>
              )}

              <button
                disabled={saving}
                type="submit"
                className="w-full rounded-xl bg-indigo-600 px-5 py-4 text-sm font-bold text-white hover:bg-indigo-700 disabled:opacity-50"
              >
                {saving ? "Saving Decision..." : "Save Review Decision"}
              </button>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
