
"use client";

import { motion, useReducedMotion } from "motion/react";
import { useCart, type CartProduct } from "./cart-provider";

export default function AddToCartButton({
  product,
}: {
  product: CartProduct;
}) {
  const { addItem, items } = useCart();
  const reducedMotion = useReducedMotion();

  const stock = product.inventory?.quantity ?? 0;
  const inCart =
    items.find((item) => item.product.id === product.id)
      ?.quantity ?? 0;

  const disabled = stock <= 0 || inCart >= stock;

  return (
    <motion.button
      type="button"
      disabled={disabled}
      onClick={() => addItem(product)}
      whileHover={reducedMotion || disabled ? undefined : { scale: 1.02 }}
      whileTap={reducedMotion || disabled ? undefined : { scale: 0.97 }}
      aria-label={`Add ${product.name} to cart`}
      className="flex w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-5 py-3.5 text-sm font-bold text-white shadow-lg shadow-indigo-100 transition-colors hover:bg-indigo-700 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:shadow-none"
    >
      <span aria-hidden="true">＋</span>
      {stock <= 0
        ? "Out of Stock"
        : inCart >= stock
          ? "Maximum Stock Added"
          : inCart > 0
            ? `Add Another (${inCart} in Cart)`
            : "Add to Cart"}
    </motion.button>
  );
}
