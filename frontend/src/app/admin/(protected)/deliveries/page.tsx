
"use client";

import { useCallback, useEffect, useState } from "react";

type Order = {
  id: number;
  order_number: string;
  customer_name: string;
  customer_phone?: string;
  status: string;
  payment_status: string;
  delivery_status?: string;
  items?: {
    id: number;
    quantity: number;
    product_name?: string;
    product_id?: number;
  }[];
};

type Delivery = {
  id: number;
  order_id: number;
  status: string;
  consignment_id: string | null;
  tracking_number?: string | null;
  last_error?: string | null;
  created_at?: string;
  updated_at?: string;
  order?: Order | null;
};

type Paginated<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
};

const statuses = [
  "pending", "processing", "created",
  "dispatched", "delivered", "failed",
];

function badge(status: string) {
  if (["created", "dispatched", "delivered", "paid", "confirmed"].includes(status)) {
    return "bg-emerald-50 text-emerald-700";
  }

  if (status === "failed" || status === "cancelled") {
    return "bg-rose-50 text-rose-700";
  }

  return "bg-amber-50 text-amber-700";
}

async function readJson(response: Response) {
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

export default function DeliveriesPage() {
  const [records, setRecords] = useState<Paginated<Delivery> | null>(null);
  const [status, setStatus] = useState("");
  const [page, setPage] = useState(1);
  const [refresh, setRefresh] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [notice, setNotice] = useState("");

  const [selected, setSelected] = useState<Delivery | null>(null);
  const [detailsLoading, setDetailsLoading] = useState(false);
  const [detailsError, setDetailsError] = useState("");
  const [retrying, setRetrying] = useState(false);

  const reload = useCallback(() => {
    setRefresh((value) => value + 1);
  }, []);

  useEffect(() => {
    const controller = new AbortController();

    async function load() {
      setLoading(true);
      setError("");

      try {
        const params = new URLSearchParams({ page: String(page) });
        if (status) params.set("status", status);

        const result = await readJson(
          await fetch(`/api/admin/deliveries?${params}`, {
            cache: "no-store",
            signal: controller.signal,
          })
        ) as Paginated<Delivery>;

        if (!controller.signal.aborted) setRecords(result);
      } catch (caught) {
        if (!controller.signal.aborted) {
          setError(
            caught instanceof Error ? caught.message : "Unable to load deliveries."
          );
        }
      } finally {
        if (!controller.signal.aborted) setLoading(false);
      }
    }

    void load();
    return () => controller.abort();
  }, [status, page, refresh]);

  async function openDetails(id: number) {
    setSelected(null);
    setDetailsError("");
    setDetailsLoading(true);

    try {
      const result = await readJson(
        await fetch(`/api/admin/deliveries/${id}`, {
          cache: "no-store",
        })
      ) as { data: Delivery };

      setSelected(result.data);
    } catch (caught) {
      setDetailsError(
        caught instanceof Error ? caught.message : "Unable to load details."
      );
    } finally {
      setDetailsLoading(false);
    }
  }

  async function retryDelivery(delivery: Delivery) {
    if (
      delivery.status !== "failed" ||
      delivery.consignment_id !== null ||
      delivery.order?.status !== "confirmed" ||
      delivery.order?.payment_status !== "paid"
    ) {
      return;
    }

    if (
      !window.confirm(
        `Queue another mock dispatch attempt for delivery #${delivery.id}?`
      )
    ) {
      return;
    }

    setRetrying(true);
    setDetailsError("");

    try {
      await readJson(
        await fetch(`/api/admin/deliveries/${delivery.id}/retry`, {
          method: "POST",
        })
      );

      setNotice(`Retry queued for delivery #${delivery.id}.`);
      setSelected(null);
      reload();
    } catch (caught) {
      setDetailsError(
        caught instanceof Error ? caught.message : "Retry failed."
      );
    } finally {
      setRetrying(false);
    }
  }

  const modalOpen = detailsLoading || selected !== null || !!detailsError;

  return (
    <div className="space-y-7">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-xs font-bold uppercase tracking-[0.18em] text-indigo-600">
            Fulfillment Operations
          </p>
          <h2 className="mt-2 text-3xl font-black tracking-tight">
            Delivery Management
          </h2>
          <p className="mt-3 text-sm text-slate-500">
            Track dispatches, review details and manage mock CarryBee retries.
          </p>
        </div>

        <button
          onClick={reload}
          className="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold hover:bg-slate-50"
        >
          ↻ Refresh
        </button>
      </div>

      {notice && (
        <p role="status" className="rounded-xl bg-emerald-50 p-4 text-sm font-semibold text-emerald-700">
          {notice}
        </p>
      )}

      <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div className="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 p-5">
          <div>
            <h3 className="font-extrabold">All Deliveries</h3>
            <p className="mt-1 text-xs text-slate-500">
              {records?.total ?? "—"} records
            </p>
          </div>

          <select
            aria-label="Filter delivery status"
            value={status}
            onChange={(event) => {
              setStatus(event.target.value);
              setPage(1);
            }}
            className="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm"
          >
            <option value="">All statuses</option>
            {statuses.map((value) => (
              <option key={value} value={value}>
                {value.charAt(0).toUpperCase() + value.slice(1)}
              </option>
            ))}
          </select>
        </div>

        {error && (
          <p role="alert" className="m-5 rounded-xl bg-rose-50 p-4 text-sm text-rose-700">
            {error}
          </p>
        )}

        <div className="overflow-x-auto">
          <table className="w-full min-w-[770px] text-left text-sm">
            <thead className="bg-slate-50 text-xs font-bold uppercase text-slate-500">
              <tr>
                <th className="px-5 py-4">Delivery</th>
                <th className="px-5 py-4">Order / Customer</th>
                <th className="px-5 py-4">Consignment</th>
                <th className="px-5 py-4">Status</th>
                <th className="px-5 py-4 text-right">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {loading ? (
                <tr><td colSpan={5} className="px-5 py-14 text-center text-slate-500">Loading deliveries...</td></tr>
              ) : !records?.data.length ? (
                <tr><td colSpan={5} className="px-5 py-14 text-center text-slate-500">No deliveries found.</td></tr>
              ) : records.data.map((delivery) => (
                <tr key={delivery.id} className="hover:bg-slate-50">
                  <td className="px-5 py-5 font-bold">
                    #{delivery.id}
                    <p className="mt-1 text-xs font-normal text-slate-400">
                      {delivery.created_at
                        ? new Date(delivery.created_at).toLocaleDateString("en-BD")
                        : "—"}
                    </p>
                  </td>
                  <td className="px-5 py-5">
                    <p className="font-bold">{delivery.order?.order_number ?? `Order #${delivery.order_id}`}</p>
                    <p className="mt-1 text-xs text-slate-500">{delivery.order?.customer_name ?? "—"}</p>
                  </td>
                  <td className="px-5 py-5 font-mono text-xs">
                    {delivery.consignment_id ?? "Not assigned"}
                  </td>
                  <td className="px-5 py-5">
                    <span className={`rounded-full px-3 py-1.5 text-xs font-bold capitalize ${badge(delivery.status)}`}>
                      {delivery.status}
                    </span>
                  </td>
                  <td className="px-5 py-5 text-right">
                    <button
                      onClick={() => void openDetails(delivery.id)}
                      className="font-bold text-indigo-600 hover:text-indigo-800"
                    >
                      View Details →
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {records && records.last_page > 1 && (
          <div className="flex items-center justify-between border-t border-slate-100 p-5">
            <span className="text-xs text-slate-500">
              Page {records.current_page} of {records.last_page}
            </span>
            <div className="flex gap-2">
              <button
                disabled={loading || page <= 1}
                onClick={() => setPage((value) => value - 1)}
                className="rounded-lg border px-4 py-2 text-xs font-bold disabled:opacity-40"
              >
                Previous
              </button>
              <button
                disabled={loading || page >= records.last_page}
                onClick={() => setPage((value) => value + 1)}
                className="rounded-lg border px-4 py-2 text-xs font-bold disabled:opacity-40"
              >
                Next
              </button>
            </div>
          </div>
        )}
      </section>

      {modalOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm">
          <div
            role="dialog"
            aria-modal="true"
            aria-label="Delivery details"
            className="max-h-[90vh] w-full max-w-xl overflow-y-auto rounded-3xl bg-white p-7 shadow-2xl"
          >
            <div className="flex items-center justify-between gap-4">
              <h3 className="text-2xl font-black">Delivery Details</h3>
              <button
                onClick={() => {
                  if (!retrying) {
                    setSelected(null);
                    setDetailsError("");
                    setDetailsLoading(false);
                  }
                }}
                disabled={retrying}
                className="rounded-xl bg-slate-100 px-3 py-2 font-bold"
              >
                ✕
              </button>
            </div>

            {detailsLoading ? (
              <p className="mt-8 text-slate-500">Loading...</p>
            ) : (
              <>
                {selected && (
                  <div className="mt-7 space-y-4 text-sm">
                    {[
                      ["Delivery ID", `#${selected.id}`],
                      ["Order", selected.order?.order_number ?? `#${selected.order_id}`],
                      ["Customer", selected.order?.customer_name ?? "—"],
                      ["Payment", selected.order?.payment_status ?? "—"],
                      ["Delivery Status", selected.status],
                      ["Consignment", selected.consignment_id ?? "Not assigned"],
                      ["Tracking", selected.tracking_number ?? "—"],
                    ].map(([label, value]) => (
                      <div key={label} className="flex justify-between gap-4 border-b border-slate-100 pb-3">
                        <span className="text-slate-500">{label}</span>
                        <span className="break-all text-right font-bold">{value}</span>
                      </div>
                    ))}

                    {selected.last_error && (
                      <p className="rounded-xl bg-rose-50 p-4 text-rose-700">
                        Last error: {selected.last_error}
                      </p>
                    )}

                    {selected.order?.items && selected.order.items.length > 0 && (
                      <div>
                        <h4 className="mb-3 font-black">Order Items</h4>
                        {selected.order.items.map((item) => (
                          <p key={item.id} className="border-b py-2 text-slate-600">
                            {item.product_name ?? `Product #${item.product_id ?? "—"}`} × {item.quantity}
                          </p>
                        ))}
                      </div>
                    )}

                    {selected.status === "failed" &&
                      selected.consignment_id === null &&
                      selected.order?.status === "confirmed" &&
                      selected.order?.payment_status === "paid" && (
                        <button
                          disabled={retrying}
                          onClick={() => void retryDelivery(selected)}
                          className="w-full rounded-xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white disabled:opacity-50"
                        >
                          {retrying ? "Queueing Retry..." : "Retry Mock Dispatch"}
                        </button>
                      )}
                  </div>
                )}

                {detailsError && (
                  <p role="alert" className="mt-5 rounded-xl bg-rose-50 p-4 text-sm text-rose-700">
                    {detailsError}
                  </p>
                )}
              </>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
