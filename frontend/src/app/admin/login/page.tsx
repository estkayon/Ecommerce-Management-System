
"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState, type FormEvent } from "react";
import { motion } from "motion/react";

export default function AdminLoginPage() {
  const router = useRouter();

  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState("");

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (loading) return;

    setLoading(true);
    setError("");

    try {
      const response = await fetch("/api/admin/login", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ email, password }),
      });

      const result = await response.json();

      if (!response.ok) {
        throw new Error(result.message ?? "Login failed.");
      }

      router.replace("/admin");
      router.refresh();
    } catch (caught) {
      setError(
        caught instanceof Error
          ? caught.message
          : "Unable to sign in."
      );
    } finally {
      setLoading(false);
    }
  }

  return (
    <main className="relative flex min-h-screen items-center justify-center overflow-hidden bg-[#080e1d] px-5 py-12 text-white">
      <div className="pointer-events-none absolute -top-48 -left-48 h-[500px] w-[500px] rounded-full bg-indigo-600/20 blur-[120px]" />
      <div className="pointer-events-none absolute -right-48 -bottom-48 h-[500px] w-[500px] rounded-full bg-blue-500/10 blur-[120px]" />

      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.45 }}
        className="relative w-full max-w-md"
      >
        <Link
          href="/"
          className="mb-10 block text-center text-3xl font-black tracking-tight"
        >
          Shop<span className="text-indigo-400">Sphere.</span>
        </Link>

        <div className="rounded-[28px] border border-white/10 bg-white/[0.055] p-8 shadow-2xl backdrop-blur-xl sm:p-10">
          <div className="mb-9">
            <span className="rounded-full border border-indigo-400/20 bg-indigo-400/10 px-3 py-1.5 text-xs font-bold text-indigo-300">
              ADMIN ACCESS
            </span>

            <h1 className="mt-6 text-3xl font-black tracking-tight">
              Welcome back.
            </h1>

            <p className="mt-3 text-sm leading-6 text-slate-400">
              Sign in to manage your store, products,
              inventory, and orders.
            </p>
          </div>

          <form onSubmit={handleSubmit} className="space-y-5">
            <div>
              <label
                htmlFor="admin-email"
                className="mb-2 block text-sm font-semibold text-slate-200"
              >
                Email address
              </label>

              <input
                id="admin-email"
                type="email"
                required
                autoComplete="username"
                value={email}
                onChange={(event) => setEmail(event.target.value)}
                placeholder="admin@example.com"
                className="w-full rounded-xl border border-white/10 bg-white/[0.07] px-4 py-3.5 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-indigo-400"
              />
            </div>

            <div>
              <label
                htmlFor="admin-password"
                className="mb-2 block text-sm font-semibold text-slate-200"
              >
                Password
              </label>

              <div className="relative">
                <input
                  id="admin-password"
                  type={showPassword ? "text" : "password"}
                  required
                  autoComplete="current-password"
                  value={password}
                  onChange={(event) => setPassword(event.target.value)}
                  placeholder="Enter your password"
                  className="w-full rounded-xl border border-white/10 bg-white/[0.07] px-4 py-3.5 pr-20 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-indigo-400"
                />

                <button
                  type="button"
                  onClick={() => setShowPassword(!showPassword)}
                  className="absolute top-1/2 right-4 -translate-y-1/2 text-xs font-bold text-indigo-300 hover:text-white"
                >
                  {showPassword ? "Hide" : "Show"}
                </button>
              </div>
            </div>

            {error && (
              <p
                role="alert"
                className="rounded-xl border border-rose-400/20 bg-rose-400/10 p-4 text-sm text-rose-300"
              >
                {error}
              </p>
            )}

            <button
              type="submit"
              disabled={loading}
              className="mt-3 w-full rounded-xl bg-indigo-500 px-5 py-4 text-sm font-bold text-white transition hover:bg-indigo-400 disabled:cursor-not-allowed disabled:opacity-60"
            >
              {loading ? "Signing in..." : "Sign In to Dashboard →"}
            </button>
          </form>

          <div className="mt-8 border-t border-white/10 pt-6 text-center">
            <Link
              href="/"
              className="text-sm text-slate-400 transition hover:text-white"
            >
              ← Return to Storefront
            </Link>
          </div>
        </div>

        <p className="mt-7 text-center text-xs text-slate-500">
          Restricted access · Authorized administrators only
        </p>
      </motion.div>
    </main>
  );
}
