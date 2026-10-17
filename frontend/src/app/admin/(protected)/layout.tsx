
import Link from "next/link";
import { cookies } from "next/headers";
import { redirect } from "next/navigation";
import AdminLogoutButton from "@/components/admin-logout-button";

type AdminUser = {
  id: number;
  name: string;
  email: string;
};

export default async function AdminProtectedLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const cookieStore = await cookies();
  const token = cookieStore.get("shopsphere_admin")?.value;

  if (!token) {
    redirect("/admin/login");
  }

  const apiUrl =
    process.env.LARAVEL_API_URL ??
    "http://127.0.0.1:8000/api";

  let user: AdminUser | null = null;

  try {
    const response = await fetch(`${apiUrl}/admin/me`, {
      headers: {
        Accept: "application/json",
        Authorization: `Bearer ${token}`,
      },
      cache: "no-store",
      signal: AbortSignal.timeout(10000),
    });

    if (response.status === 401 || response.status === 403) {
      redirect("/admin/login");
    }

    if (!response.ok) {
      throw new Error("Admin service unavailable.");
    }

    user = (await response.json()) as AdminUser;
  } catch (error) {
    // Next.js redirect must not be swallowed by error handling.
    if (
      error &&
      typeof error === "object" &&
      "digest" in error &&
      typeof error.digest === "string" &&
      error.digest.startsWith("NEXT_REDIRECT")
    ) {
      throw error;
    }

    throw new Error(
      "Unable to verify admin session. Please try again."
    );
  }

  return (
    <div className="min-h-screen bg-slate-50 text-slate-900">
      <div className="flex min-h-screen flex-col lg:flex-row">
        <aside className="flex w-full flex-col bg-[#0f172a] p-7 text-white lg:min-h-screen lg:w-72">
          <Link
            href="/admin"
            className="text-2xl font-black tracking-tight"
          >
            Shop<span className="text-indigo-400">Sphere.</span>
          </Link>

          <p className="mt-2 text-xs tracking-[0.22em] text-slate-400 uppercase">
            Administration
          </p>

          <nav className="mt-10 flex flex-wrap gap-2 lg:flex-col">
            <Link
              href="/admin"
              className="rounded-xl bg-indigo-600 px-4 py-3 text-sm font-semibold text-white"
            >
              Dashboard Overview
            </Link>

            <Link
              href="/admin/products"
              className="rounded-xl px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-white/10"
            >
              Product Management
          </Link>

          <Link
            href="/admin/products"
            className="rounded-xl px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-white/10"
          >
            Inventory Control
          </Link>

          <Link
            href="/admin/orders"
            className="rounded-xl px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-white/10"
          >
            Orders & Payments
          </Link>

          <Link
            href="/admin/deliveries"
            className="rounded-xl px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-white/10"
          >
            Delivery Management
          </Link>

          <Link
            href="/admin/payment-reviews"
            className="rounded-xl px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-white/10"
          >
            Payment Review
          </Link>

           <Link
              href="/"
              target="_blank"
              rel="noopener noreferrer"
              className="rounded-xl px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-white/10"
            >
              Visit Storefront ↗
          </Link>
          </nav>

          <div className="mt-auto pt-12">
            <div className="mb-5 border-t border-white/10 pt-6">
              <p className="text-sm font-semibold">
                {user.name}
              </p>
              <p className="mt-1 break-all text-xs text-slate-400">
                {user.email}
              </p>
            </div>

            <AdminLogoutButton />
          </div>
        </aside>

        <main className="min-w-0 flex-1">
          <header className="border-b border-slate-200 bg-white px-6 py-6 sm:px-10">
            <div className="mx-auto flex max-w-6xl items-center justify-between gap-4">
              <div>
                <p className="text-xs font-bold tracking-[0.18em] text-indigo-600 uppercase">
                  Control Center
                </p>
                <h1 className="mt-1 text-xl font-black tracking-tight">
                  Admin Dashboard
                </h1>
              </div>

              <span className="rounded-full bg-emerald-50 px-4 py-2 text-xs font-bold text-emerald-700">
                Admin Session
              </span>
            </div>
          </header>

          <div className="mx-auto max-w-6xl p-6 sm:p-10">
            {children}
          </div>
        </main>
      </div>
    </div>
  );
}
