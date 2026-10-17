
import Link from "next/link";
import { cookies } from "next/headers";

export const dynamic = "force-dynamic";

type DashboardData = {
  total_products: number;
  total_orders: number;
  paid_orders: number;
  pending_orders: number;
  verified_revenue: string;
  unresolved_payments: number;
  deliveries: {
    total: number;
    pending: number;
    processing: number;
    created: number;
    dispatched: number;
    delivered: number;
    failed: number;
  };
};

type DashboardResponse = {
  data: DashboardData;
};

function money(value: string | number) {
  return new Intl.NumberFormat("en-BD", {
    style: "currency",
    currency: "BDT",
    maximumFractionDigits: 2,
  }).format(Number(value));
}

async function getDashboard(): Promise<DashboardData | null> {
  const token = (await cookies())
    .get("shopsphere_admin")?.value;

  if (!token) return null;

  const apiUrl =
    process.env.LARAVEL_API_URL ??
    "http://127.0.0.1:8000/api";

  try {
    const response = await fetch(
      `${apiUrl}/admin/dashboard`,
      {
        headers: {
          Accept: "application/json",
          Authorization: `Bearer ${token}`,
        },
        cache: "no-store",
        signal: AbortSignal.timeout(10000),
      }
    );

    if (!response.ok) return null;

    const result =
      (await response.json()) as DashboardResponse;

    return result.data ?? null;
  } catch {
    return null;
  }
}

export default async function AdminDashboardPage() {
  const data = await getDashboard();

  const metrics = [
    {
      label: "Total Products",
      value: data?.total_products.toLocaleString("en-BD") ?? "—",
      detail: "Products in catalog",
      symbol: "◈",
      accent: "bg-indigo-50 text-indigo-600",
    },
    {
      label: "Total Orders",
      value: data?.total_orders.toLocaleString("en-BD") ?? "—",
      detail: "All customer orders",
      symbol: "▣",
      accent: "bg-blue-50 text-blue-600",
    },
    {
      label: "Verified Revenue",
      value: data ? money(data.verified_revenue) : "—",
      detail: "Confirmed and paid orders",
      symbol: "৳",
      accent: "bg-emerald-50 text-emerald-600",
    },
    {
      label: "Paid Orders",
      value: data?.paid_orders.toLocaleString("en-BD") ?? "—",
      detail: "Confirmed payment orders",
      symbol: "✓",
      accent: "bg-teal-50 text-teal-600",
    },
    {
      label: "Pending Orders",
      value: data?.pending_orders.toLocaleString("en-BD") ?? "—",
      detail: "Awaiting order resolution",
      symbol: "◷",
      accent: "bg-amber-50 text-amber-600",
    },
    {
      label: "Unresolved Payments",
      value: data?.unresolved_payments.toLocaleString("en-BD") ?? "—",
      detail: "Pending payment attempts",
      symbol: "!",
      accent: "bg-rose-50 text-rose-600",
    },
  ];

  const deliveryStats = data
    ? [
        { label: "Pending", value: data.deliveries.pending },
        { label: "Processing", value: data.deliveries.processing },
        { label: "Created", value: data.deliveries.created },
        { label: "Dispatched", value: data.deliveries.dispatched },
        { label: "Delivered", value: data.deliveries.delivered },
        { label: "Failed", value: data.deliveries.failed },
      ]
    : [];

  const modules = [
    {
      title: "Product Management",
      description: "Create, edit and manage your catalog.",
      href: "/admin/products",
      symbol: "◈",
    },
    {
      title: "Inventory Control",
      description: "Track stock and inventory adjustments.",
      href: "/admin/products",
      symbol: "▤",
    },
    {
      title: "Orders & Payments",
      description: "Review transactions and customer orders.",
      href: "/admin/orders",
      symbol: "▣",
    },
    {
      title: "Delivery Management",
      description: "Monitor CarryBee mock consignments.",
      href: "/admin/deliveries",
      symbol: "↗",
    },
    {
      title: "Payment Review",
      description: "Investigate flagged payment transactions.",
      href: "/admin/payment-reviews",
      symbol: "◎",
    },
  ];

  return (
    <div className="space-y-9">
      <section className="flex flex-wrap items-end justify-between gap-5">
        <div>
          <p className="text-xs font-bold uppercase tracking-[0.2em] text-indigo-600">
            Store Intelligence
          </p>

          <h2 className="mt-2 text-3xl font-black tracking-tight sm:text-4xl">
            Dashboard Overview
          </h2>

          <p className="mt-3 text-sm text-slate-500">
            Live insights into your ShopSphere operations.
          </p>
        </div>

        <div className="flex flex-wrap gap-3">
          <Link
            href="/admin"
            className="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50"
          >
            ↻ Refresh
          </Link>

          <Link
            href="/"
            target="_blank"
            rel="noopener noreferrer"
            className="rounded-xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-indigo-700"
          >
            View Store ↗
          </Link>
        </div>
      </section>

      {!data && (
        <div
          role="alert"
          className="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800"
        >
          Dashboard metrics are temporarily unavailable.
          Please check the Laravel API connection and refresh.
        </div>
      )}

      <section className="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
        {metrics.map((metric) => (
          <div
            key={metric.label}
            className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
          >
            <div className="flex items-start justify-between gap-4">
              <p className="text-sm font-semibold text-slate-500">
                {metric.label}
              </p>

              <span
                className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-xl font-black ${metric.accent}`}
              >
                {metric.symbol}
              </span>
            </div>

            <p className="mt-5 break-words text-3xl font-black tracking-tight">
              {metric.value}
            </p>

            <p className="mt-3 text-xs text-slate-400">
              {metric.detail}
            </p>
          </div>
        ))}
      </section>

      <section className="grid gap-6 xl:grid-cols-[1fr_0.9fr]">
        <div className="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm">
          <div className="flex items-center justify-between gap-4">
            <div>
              <h3 className="text-xl font-black">
                Delivery Overview
              </h3>
              <p className="mt-2 text-sm text-slate-500">
                Mock CarryBee fulfillment status
              </p>
            </div>

            <span className="rounded-xl bg-indigo-50 px-4 py-3 text-lg font-black text-indigo-600">
              {data?.deliveries.total ?? "—"}
            </span>
          </div>

          <div className="mt-7 grid grid-cols-2 gap-3 sm:grid-cols-3">
            {deliveryStats.length > 0 ? (
              deliveryStats.map((item) => (
                <div
                  key={item.label}
                  className="rounded-xl bg-slate-50 p-4"
                >
                  <p className="text-xs font-medium text-slate-500">
                    {item.label}
                  </p>
                  <p className="mt-2 text-2xl font-black">
                    {item.value.toLocaleString("en-BD")}
                  </p>
                </div>
              ))
            ) : (
              <p className="col-span-full text-sm text-slate-500">
                Delivery data unavailable.
              </p>
            )}
          </div>

          <Link
            href="/admin/deliveries"
            className="mt-7 inline-block text-sm font-bold text-indigo-600 hover:text-indigo-800"
          >
            Manage Deliveries →
          </Link>
        </div>

        <div className="rounded-2xl bg-[#0f172a] p-7 text-white shadow-sm">
          <p className="text-xs font-bold uppercase tracking-[0.2em] text-indigo-300">
            Quick Access
          </p>

          <h3 className="mt-3 text-2xl font-black">
            Store Operations
          </h3>

          <p className="mt-3 text-sm leading-6 text-slate-400">
            Everything you need to run your store,
            organized in one place.
          </p>

          <div className="mt-7 space-y-3">
            {modules.map((module) => (
              <Link
                key={module.title}
                href={module.href}
                className="flex items-center justify-between gap-3 rounded-xl border border-white/10 bg-white/5 px-4 py-3 transition hover:bg-white/10"
              >
                <span className="text-sm font-semibold">
                  {module.title}
                </span>
                <span className="text-indigo-300">↗</span>
              </Link>
            ))}
          </div>
        </div>
      </section>

      <section>
        <div className="mb-5">
          <h3 className="text-xl font-black">
            Management Modules
          </h3>
          <p className="mt-2 text-sm text-slate-500">
            Access your day-to-day management tools.
          </p>
        </div>

        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {modules.map((module) => (
            <Link
              key={module.title}
              href={module.href}
              className="group rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-md"
            >
              <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-50 text-xl font-bold text-indigo-600">
                {module.symbol}
              </div>

              <h4 className="mt-5 text-base font-extrabold">
                {module.title}
              </h4>

              <p className="mt-2 text-sm leading-6 text-slate-500">
                {module.description}
              </p>

              <p className="mt-5 text-sm font-bold text-indigo-600">
                Open Module ↗
              </p>
            </Link>
          ))}
        </div>
      </section>
    </div>
  );
}
