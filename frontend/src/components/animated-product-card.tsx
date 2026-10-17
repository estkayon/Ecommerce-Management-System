
"use client";

import Link from "next/link";
import { motion, useReducedMotion } from "motion/react";
import AddToCartButton from "@/components/add-to-cart-button";
import type { CartProduct } from "@/components/cart-provider";

type Product = CartProduct & {
  description: string | null;
};

function formatPrice(value: string | number) {
  return new Intl.NumberFormat("en-BD", {
    style: "currency",
    currency: "BDT",
    maximumFractionDigits: 2,
  }).format(Number(value));
}

export default function AnimatedProductCard({
  product,
  index,
}: {
  product: Product;
  index: number;
}) {
  const reducedMotion = useReducedMotion();
  const inStock = (product.inventory?.quantity ?? 0) > 0;

  const initials = product.name
    .split(" ")
    .slice(0, 2)
    .map((word) => word[0])
    .join("")
    .toUpperCase();

  return (
    <motion.article
      initial={reducedMotion ? false : { opacity: 0, y: 25 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, amount: 0.1 }}
      transition={{
        duration: 0.5,
        delay: reducedMotion ? 0 : (index % 4) * 0.07,
      }}
      whileHover={reducedMotion ? undefined : { y: -6 }}
      className="group flex h-full flex-col overflow-hidden rounded-[24px] border border-slate-200/80 bg-white shadow-sm transition-shadow duration-300 hover:shadow-2xl hover:shadow-indigo-950/10"
    >
      <Link
        href={`/products/${product.id}`}
        className="relative flex aspect-[5/4] items-center justify-center overflow-hidden bg-gradient-to-br from-[#f1f5ff] via-[#eef2ff] to-[#e2e8f0]"
      >
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_75%_25%,rgba(99,102,241,0.15),transparent_55%)]" />

        <span className="absolute left-4 top-4 z-10 rounded-full border border-white/70 bg-white/80 px-3 py-1.5 text-[10px] font-bold tracking-wider text-slate-600 backdrop-blur">
          COLLECTION / {String(index + 1).padStart(2, "0")}
        </span>

        <motion.div
          whileHover={reducedMotion ? undefined : { scale: 1.1, rotate: -5 }}
          transition={{ type: "spring", stiffness: 240, damping: 18 }}
          className="relative flex h-28 w-28 items-center justify-center rounded-[32px] border border-white/80 bg-white/65 text-5xl font-black tracking-tight text-indigo-400 shadow-xl shadow-indigo-200/50 backdrop-blur sm:h-32 sm:w-32"
        >
          {initials}
        </motion.div>

        <span className="absolute bottom-4 right-4 rounded-full bg-white/90 px-3 py-1.5 text-xs font-semibold text-indigo-700 opacity-0 transition-opacity group-hover:opacity-100">
          Explore ↗
        </span>
      </Link>

      <div className="flex flex-1 flex-col p-5">
        <div className="flex items-center justify-between gap-3">
          <p className="text-[11px] font-semibold tracking-wide text-slate-400">
            {product.sku}
          </p>
          <span
            className={`inline-flex items-center gap-1.5 text-[11px] font-semibold ${
              inStock ? "text-emerald-700" : "text-rose-600"
            }`}
          >
            <span
              className={`h-1.5 w-1.5 rounded-full ${
                inStock ? "bg-emerald-500" : "bg-rose-500"
              }`}
            />
            {inStock ? "Available" : "Unavailable"}
          </span>
        </div>

        <Link
          href={`/products/${product.id}`}
          className="mt-4 block"
        >
          <h3 className="min-h-12 text-lg font-bold leading-6 tracking-tight text-slate-900 transition group-hover:text-indigo-600">
            {product.name}
          </h3>
        </Link>

        <p className="mt-2 line-clamp-2 min-h-10 text-sm leading-5 text-slate-500">
          {product.description ||
            "Explore this essential from our collection."}
        </p>

        <div className="mt-auto pt-6">
          <div className="mb-5 border-t border-slate-100 pt-4">
            <p className="text-[11px] font-semibold tracking-widest text-slate-400 uppercase">
              Price
            </p>
            <p className="mt-1 text-2xl font-extrabold tracking-tight text-slate-950">
              {formatPrice(product.price)}
            </p>
          </div>

          <AddToCartButton product={product} />

          <Link
            href={`/products/${product.id}`}
            className="mt-4 block text-center text-xs font-semibold text-slate-500 transition hover:text-indigo-600"
          >
            View Product Details ↗
          </Link>
        </div>
      </div>
    </motion.article>
  );
}
