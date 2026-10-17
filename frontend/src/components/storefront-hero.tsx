
"use client";

import Link from "next/link";
import { motion, useReducedMotion } from "motion/react";
import { useCart } from "@/components/cart-provider";

const ease = [0.22, 1, 0.36, 1] as const;

export default function StorefrontHero() {
  const { count } = useCart();
  const reducedMotion = useReducedMotion();

  return (
    <>
      <div className="bg-[#0b1120] px-4 py-2.5 text-center text-xs font-medium tracking-wide text-slate-300">
        Thoughtfully selected tech. Everyday convenience.
      </div>

      <header className="sticky top-0 z-50 border-b border-slate-200/70 bg-white/90 backdrop-blur-xl">
        <div className="mx-auto flex h-20 max-w-7xl items-center justify-between gap-4 px-5 sm:px-8">
          <Link href="/" className="flex items-center gap-3">
            <div className="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#4f46e5] text-xl font-black text-white shadow-lg shadow-indigo-200">
              S
            </div>
            <div>
              <p className="text-xl font-extrabold tracking-tight text-slate-950">
                ShopSphere
              </p>
              <p className="text-[10px] font-semibold tracking-[0.18em] text-slate-400 uppercase">
                Modern Essentials
              </p>
            </div>
          </Link>

          <nav className="hidden items-center gap-9 text-sm font-semibold text-slate-600 md:flex">
            <a href="#products" className="transition hover:text-indigo-600">
              Shop
            </a>
            <a href="#benefits" className="transition hover:text-indigo-600">
              Why Us
            </a>
            <Link href="/cart" className="transition hover:text-indigo-600">
              Shopping Bag
            </Link>
          </nav>

          <Link
            href="/cart"
            className="group flex items-center gap-3 rounded-full border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-900 transition hover:border-indigo-200 hover:bg-indigo-50"
          >
            <svg
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="1.8"
              className="h-5 w-5"
              aria-hidden="true"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M4 8h16l-1.3 12H5.3L4 8Zm4 1V6a4 4 0 0 1 8 0v3"
              />
            </svg>
            <span className="hidden sm:inline">Cart</span>
            <span className="flex h-6 min-w-6 items-center justify-center rounded-full bg-indigo-600 px-1 text-xs font-bold text-white">
              {count}
            </span>
          </Link>
        </div>
      </header>

      <section className="relative isolate overflow-hidden bg-[#0b1226] text-white">
        <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_75%_40%,rgba(99,102,241,0.28),transparent_50%)]" />
        <div className="pointer-events-none absolute -left-32 bottom-0 h-80 w-80 rounded-full bg-sky-600/10 blur-3xl" />

        <div className="relative mx-auto grid min-h-[590px] max-w-7xl items-center gap-14 px-5 py-20 sm:px-8 lg:grid-cols-2 lg:py-28">
          <div className="relative z-10">
            <motion.div
              initial={reducedMotion ? false : { opacity: 0, y: 18 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.6, ease }}
              className="inline-flex items-center gap-2 rounded-full border border-indigo-300/20 bg-indigo-300/10 px-4 py-2 text-xs font-semibold text-indigo-200"
            >
              <span className="h-1.5 w-1.5 rounded-full bg-indigo-300" />
              CURATED FOR MODERN LIVING
            </motion.div>

            <motion.h1
              initial={reducedMotion ? false : { opacity: 0, y: 28 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.75, delay: 0.1, ease }}
              className="mt-8 max-w-2xl text-5xl leading-[1.12] font-black tracking-[-0.05em] sm:text-6xl lg:text-[76px]"
            >
              Everyday tech.
              <span className="mt-2 block bg-gradient-to-r from-indigo-300 via-violet-300 to-sky-300 bg-clip-text text-transparent">
                Elevated.
              </span>
            </motion.h1>

            <motion.p
              initial={reducedMotion ? false : { opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.65, delay: 0.23, ease }}
              className="mt-7 max-w-lg text-base leading-8 text-slate-300 sm:text-lg"
            >
              Discover smart accessories and practical essentials
              designed to make everyday moments a little better.
            </motion.p>

            <motion.div
              initial={reducedMotion ? false : { opacity: 0, y: 16 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.6, delay: 0.36, ease }}
              className="mt-10 flex flex-wrap items-center gap-4"
            >
              <motion.a
                href="#products"
                whileHover={reducedMotion ? undefined : { y: -3 }}
                whileTap={reducedMotion ? undefined : { scale: 0.98 }}
                className="inline-flex items-center gap-3 rounded-2xl bg-indigo-500 px-7 py-4 text-sm font-bold text-white shadow-xl shadow-indigo-900/30 transition hover:bg-indigo-400"
              >
                Shop Collection
                <span aria-hidden="true">↗</span>
              </motion.a>

              <a
                href="#benefits"
                className="rounded-2xl border border-white/20 px-7 py-4 text-sm font-semibold text-slate-200 transition hover:bg-white/10"
              >
                Discover More
              </a>
            </motion.div>

            <div className="mt-14 flex items-center gap-8 border-t border-white/10 pt-7">
              <div>
                <p className="text-2xl font-extrabold">36+</p>
                <p className="mt-1 text-xs text-slate-400">
                  Demo Products
                </p>
              </div>
              <div className="h-9 w-px bg-white/15" />
              <div>
                <p className="text-2xl font-extrabold">100+</p>
                <p className="mt-1 text-xs text-slate-400">
                  Demo Orders
                </p>
              </div>
            </div>
          </div>

          <motion.div
            initial={
              reducedMotion
                ? false
                : { opacity: 0, y: 35, rotate: -4 }
            }
            animate={{ opacity: 1, y: 0, rotate: -5 }}
            transition={{ duration: 0.9, delay: 0.25, ease }}
            className="relative mx-auto hidden w-full max-w-[450px] lg:block"
          >
            <div className="absolute inset-8 rounded-[4rem] bg-indigo-500/30 blur-[85px]" />

            <div className="relative overflow-hidden rounded-[42px] border border-white/20 bg-gradient-to-br from-white/20 via-indigo-300/10 to-white/5 p-9 shadow-2xl backdrop-blur-xl">
              <div className="flex items-center justify-between">
                <span className="rounded-full border border-white/20 px-4 py-2 text-xs font-semibold tracking-wider text-indigo-100">
                  THE EDIT
                </span>
                <span className="text-4xl text-indigo-200">✳</span>
              </div>

              <div className="mt-24">
                <div className="flex h-32 w-32 items-center justify-center rounded-[32px] border border-white/20 bg-white/15 text-7xl shadow-2xl">
                  ✦
                </div>

                <p className="mt-16 text-sm font-semibold tracking-[0.2em] text-indigo-200 uppercase">
                  ShopSphere Selection
                </p>
                <h2 className="mt-4 text-5xl leading-tight font-black tracking-tight">
                  Small details.
                  <br />
                  Big difference.
                </h2>
                <p className="mt-5 text-sm leading-7 text-slate-300">
                  Function meets thoughtful design.
                </p>
              </div>
            </div>

            <motion.div
              animate={
                reducedMotion ? undefined : { y: [0, -10, 0] }
              }
              transition={{
                duration: 5,
                repeat: Infinity,
                ease: "easeInOut",
              }}
              className="absolute -right-7 top-20 rounded-2xl border border-white/20 bg-white/95 px-5 py-4 text-sm font-bold text-slate-900 shadow-xl"
            >
              ✨ New discoveries
            </motion.div>
          </motion.div>
        </div>
      </section>
    </>
  );
}
