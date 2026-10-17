
"use client";

import {
  useEffect,
  useState,
  type FormEvent,
} from "react";

type RecordValue = string | number | null | undefined;

type Order = {
  id: number;
  order_number: string;
  customer_name: string;
  customer_phone?: string;
  total: RecordValue;
  status: string;
  payment_status: string;
  delivery_status?: string;
  created_at?: string;
};

type OrderItem = {
  id: number;
  product_id?: number;
  product_name?: string;
  name?: string;
  quantity?: number;
  unit_price?: RecordValue;
  price?: RecordValue;
  subtotal?: RecordValue;
  total?: RecordValue;
};

type Payment = {
  id: number;
  order_id: number;
  transaction_id: string;
  amount: RecordValue;
  currency?: string;
  status: string;
  gateway?: string;
  verified_at?: string | null;
  created_at?: string;
  order?: {
    order_number: string;
    customer_name: string;
    status: string;
  } | null;
};

type Paged<T> = {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
};

type OrderDetails = {
  order: Order & Record<string, unknown>;
  items: OrderItem[];
};

type Tab = "orders" | "payments";

function money(value: RecordValue) {
  const number = Number(value);

  if (value == null || !Number.isFinite(number)) return "—";

  return new Intl.NumberFormat("en-BD", {
    style: "currency",
    currency: "BDT",
    maximumFractionDigits: 2,
  }).format(number);
}

function dateTime(value?: string | null) {
  if (!value) return "—";
  const date = new Date(value);

  return Number.isNaN(date.getTime())
    ? value
    : date.toLocaleString("en-BD");
}

function StatusBadge({ status }: { status?: string }) {
  const text = status || "unknown";

  const color =
    ["paid", "confirmed", "delivered", "completed"].includes(text)
      ? "bg-emerald-50 text-emerald-700"
      : ["failed", "cancelled", "expired"].includes(text)
        ? "bg-rose-50 text-rose-700"
        : "bg-amber-50 text-amber-700";

  return (
    <span
      className={`inline-flex rounded-full px-3 py-1.5 text-xs font-bold capitalize ${color}`}
    >
      {text.replaceAll("_", " ")}
    </span>
  );
}

async function getJson<T>(url: string): Promise<T> {
  const response = await fetch(url, { cache: "no-store" });
  const result = await response.json();

  if (!response.ok) {
    throw new Error(result.message || "Unable to load data.");
  }

  return result as T;
}

export default function AdminOrdersPage() {
  const [tab, setTab] = useState<Tab>("orders");
  const [searchInput, setSearchInput] = useState("");
  const [search, setSearch] = useState("");
  const [statusFilter, setStatusFilter] = useState("");
  const [page, setPage] = useState(1);
  const [refresh, setRefresh] = useState(0);

  const [orders, setOrders] = useState<Paged<Order> | null>(null);
  const [payments, setPayments] =
    useState<Paged<Payment> | null>(null);

  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  const [details, setDetails] = useState<OrderDetails | null>(null);
  const [detailsLoading, setDetailsLoading] = useState(false);
  const [detailsError, setDetailsError] = useState("");

  useEffect(() => {
    let active = true;

    async function load() {
      setLoading(true);
      setError("");

      const params = new URLSearchParams({
        page: String(page),
      });

      if (search) params.set("search", search);
      if (statusFilter) params.set("status", statusFilter);

      try {
        if (tab === "orders") {
          const data = await getJson<Paged<Order>>(
            `/api/admin/orders?${params.toString()}`
          );

          if (active) setOrders(data);
        } else {
          const data = await getJson<Paged<Payment>>(
            `/api/admin/payments?${params.toString()}`
          );

          if (active) setPayments(data);
        }
      } catch (caught) {
        if (active) {
          setError(
            caught instanceof Error
              ? caught.message
              : "Unable to load records."
          );
        }
      } finally {
        if (active) setLoading(false);
      }
    }

    void load();

    return () => {
      active = false;
    };
  }, [tab, search, statusFilter, page, refresh]);

  function changeTab(next: Tab) {
    setTab(next);
    setPage(1);
    setSearch("");
    setSearchInput("");
    setStatusFilter("");
    setError("");
  }

  function handleSearch(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setPage(1);
    setSearch(searchInput.trim());
  }

  async function openOrder(id: number) {
    setDetails(null);
    setDetailsError("");
    setDetailsLoading(true);

    try {
      const result = await getJson<{ data: OrderDetails }>(
        `/api/admin/orders/${id}`
      );

      setDetails(result.data);
    } catch (caught) {
      setDetailsError(
        caught instanceof Error
          ? caught.message
          : "Unable to load order."
      );
    } finally {
      setDetailsLoading(false);
    }
  }

  const current = tab === "orders" ? orders : payments;

  return (
    <div className="space-y-7">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-xs font-bold tracking-[0.18em] text-indigo-600 uppercase">
            Operations
          </p>
          <h2 className="mt-2 text-3xl font-black tracking-tight">
            Orders & Payments
          </h2>
          <p className="mt-3 text-sm text-slate-500">
            Monitor customer orders and SSLCommerz transactions.
          </p>
        </div>

        <button
          type="button"
          onClick={() => setRefresh((value) => value + 1)}
          className="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold hover:bg-slate-50"
        >
          ↻ Refresh
        </button>
      </div>

      <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div className="flex gap-2 border-b border-slate-100 p-4">
          {(["orders", "payments"] as const).map((value) => (
            <button
              key={value}
              onClick={() => changeTab(value)}
              className={`rounded-xl px-5 py-3 text-sm font-bold capitalize transition ${
                tab === value
                  ? "bg-indigo-600 text-white"
                  : "text-slate-500 hover:bg-slate-100"
              }`}
            >
              {value}
            </button>
          ))}
        </div>

        <div className="flex flex-wrap gap-3 border-b border-slate-100 p-5">
          <form
            onSubmit={handleSearch}
            className="flex min-w-0 flex-1 gap-2"
          >
            <input
              value={searchInput}
              onChange={(event) => setSearchInput(event.target.value)}
              placeholder={
                tab === "orders"
                  ? "Search order, customer or phone"
                  : "Search transaction, order or customer"
              }
              className="min-w-0 flex-1 rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-indigo-500"
            />

            <button
              type="submit"
              className="rounded-xl bg-slate-900 px-5 py-3 text-sm font-bold text-white"
            >
              Search
            </button>
          </form>

          <select
            aria-label="Filter by status"
            value={statusFilter}
            onChange={(event) => {
              setPage(1);
              setStatusFilter(event.target.value);
            }}
            className="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold outline-none"
          >
            <option value="">All statuses</option>
            {(tab === "orders"
              ? ["pending", "confirmed", "cancelled", "expired"]
              : ["initiating", "pending", "processing", "paid", "failed"]
            ).map((status) => (
              <option key={status} value={status}>
                {status}
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
          <table className="w-full min-w-[800px] text-left text-sm">
            <thead className="bg-slate-50 text-xs font-bold text-slate-500 uppercase">
              {tab === "orders" ? (
                <tr>
                  <th className="px-5 py-4">Order</th>
                  <th className="px-5 py-4">Customer</th>
                  <th className="px-5 py-4">Amount</th>
                  <th className="px-5 py-4">Order Status</th>
                  <th className="px-5 py-4">Payment</th>
                  <th className="px-5 py-4">Action</th>
                </tr>
              ) : (
                <tr>
                  <th className="px-5 py-4">Transaction</th>
                  <th className="px-5 py-4">Order</th>
                  <th className="px-5 py-4">Amount</th>
                  <th className="px-5 py-4">Status</th>
                  <th className="px-5 py-4">Created</th>
                </tr>
              )}
            </thead>

            <tbody className="divide-y divide-slate-100">
              {loading ? (
                <tr>
                  <td colSpan={6} className="px-5 py-14 text-center text-slate-500">
                    Loading {tab}...
                  </td>
                </tr>
              ) : tab === "orders" ? (
                !orders?.data.length ? (
                  <tr>
                    <td colSpan={6} className="px-5 py-14 text-center text-slate-500">
                      No orders found.
                    </td>
                  </tr>
                ) : (
                  orders.data.map((order) => (
                    <tr key={order.id} className="hover:bg-slate-50">
                      <td className="px-5 py-5">
                        <p className="font-bold">{order.order_number}</p>
                        <p className="mt-1 text-xs text-slate-400">
                          #{order.id} · {dateTime(order.created_at)}
                        </p>
                      </td>
                      <td className="px-5 py-5">{order.customer_name}</td>
                      <td className="px-5 py-5 font-bold">{money(order.total)}</td>
                      <td className="px-5 py-5">
                        <StatusBadge status={order.status} />
                      </td>
                      <td className="px-5 py-5">
                        <StatusBadge status={order.payment_status} />
                      </td>
                      <td className="px-5 py-5">
                        <button
                          onClick={() => void openOrder(order.id)}
                          className="font-bold text-indigo-600 hover:text-indigo-800"
                        >
                          View Details →
                        </button>
                      </td>
                    </tr>
                  ))
                )
              ) : !payments?.data.length ? (
                <tr>
                  <td colSpan={5} className="px-5 py-14 text-center text-slate-500">
                    No payments found.
                  </td>
                </tr>
              ) : (
                payments.data.map((payment) => (
                  <tr key={payment.id} className="hover:bg-slate-50">
                    <td className="px-5 py-5">
                      <p className="max-w-[230px] break-all font-mono text-xs font-bold">
                        {payment.transaction_id}
                      </p>
                      <p className="mt-1 text-xs text-slate-400">
                        Payment #{payment.id}
                      </p>
                    </td>
                    <td className="px-5 py-5">
                      <p className="font-bold">
                        {payment.order?.order_number ?? `#${payment.order_id}`}
                      </p>
                      <p className="mt-1 text-xs text-slate-400">
                        {payment.order?.customer_name ?? "—"}
                      </p>
                    </td>
                    <td className="px-5 py-5 font-bold">{money(payment.amount)}</td>
                    <td className="px-5 py-5">
                      <StatusBadge status={payment.status} />
                    </td>
                    <td className="px-5 py-5 text-xs text-slate-500">
                      {dateTime(payment.created_at)}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {current && (
          <div className="flex flex-wrap items-center justify-between gap-4 border-t border-slate-100 p-5">
            <p className="text-xs text-slate-500">
              {current.total} records · Page {current.current_page} of{" "}
              {current.last_page}
            </p>

            <div className="flex gap-2">
              <button
                disabled={loading || page <= 1}
                onClick={() => setPage((value) => value - 1)}
                className="rounded-lg border border-slate-200 px-4 py-2 text-xs font-bold disabled:opacity-40"
              >
                Previous
              </button>
              <button
                disabled={loading || page >= current.last_page}
                onClick={() => setPage((value) => value + 1)}
                className="rounded-lg border border-slate-200 px-4 py-2 text-xs font-bold disabled:opacity-40"
              >
                Next
              </button>
            </div>
          </div>
        )}
      </section>

      {(detailsLoading || details || detailsError) && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm">
          <div
            role="dialog"
            aria-modal="true"
            aria-label="Order details"
            className="max-h-[88vh] w-full max-w-2xl overflow-y-auto rounded-3xl bg-white p-7 shadow-2xl sm:p-9"
          >
            <div className="flex items-start justify-between gap-4">
              <h3 className="text-2xl font-black">
                Order Details
              </h3>
              <button
                onClick={() => {
                  setDetails(null);
                  setDetailsError("");
                  setDetailsLoading(false);
                }}
                className="rounded-lg bg-slate-100 px-3 py-2 font-bold"
              >
                ✕
              </button>
            </div>

            {detailsLoading ? (
              <p className="mt-8 text-slate-500">Loading details...</p>
            ) : detailsError ? (
              <p className="mt-8 text-rose-600">{detailsError}</p>
            ) : details && (
              <div className="mt-7 space-y-6">
                <div className="grid gap-4 rounded-2xl bg-slate-50 p-5 text-sm sm:grid-cols-2">
                  <div>
                    <p className="text-slate-500">Order Number</p>
                    <p className="mt-1 font-bold">{details.order.order_number}</p>
                  </div>
                  <div>
                    <p className="text-slate-500">Customer</p>
                    <p className="mt-1 font-bold">{details.order.customer_name}</p>
                  </div>
                  <div>
                    <p className="text-slate-500">Order Status</p>
                    <div className="mt-2">
                      <StatusBadge status={details.order.status} />
                    </div>
                  </div>
                  <div>
                    <p className="text-slate-500">Payment Status</p>
                    <div className="mt-2">
                      <StatusBadge status={details.order.payment_status} />
                    </div>
                  </div>
                  <div>
                    <p className="text-slate-500">Total</p>
                    <p className="mt-1 font-bold">{money(details.order.total)}</p>
                  </div>
                  <div>
                    <p className="text-slate-500">Created</p>
                    <p className="mt-1 font-bold">
                      {dateTime(details.order.created_at)}
                    </p>
                  </div>
                </div>

                <div>
                  <h4 className="mb-4 text-lg font-black">
                    Purchased Items
                  </h4>

                  <div className="divide-y divide-slate-100 rounded-xl border border-slate-200">
                    {details.items.length === 0 ? (
                      <p className="p-5 text-sm text-slate-500">
                        No order items found.
                      </p>
                    ) : (
                      details.items.map((item) => (
                        <div
                          key={item.id}
                          className="flex items-center justify-between gap-4 p-5 text-sm"
                        >
                          <div>
                            <p className="font-bold">
                              {item.product_name ??
                                item.name ??
                                `Product #${item.product_id ?? "—"}`}
                            </p>
                            <p className="mt-1 text-xs text-slate-500">
                              Quantity: {item.quantity ?? "—"}
                            </p>
                          </div>
                          <p className="font-bold">
                            {money(
                              item.subtotal ??
                                item.total ??
                                item.unit_price ??
                                item.price
                            )}
                          </p>
                        </div>
                      ))
                    )}
                  </div>
                </div>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
