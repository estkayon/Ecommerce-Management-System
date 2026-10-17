
"use client";

import Link from "next/link";
import { useCart } from "@/components/cart-provider";

const formatPrice = (value: number) =>
  new Intl.NumberFormat("en-BD", {
    style: "currency",
    currency: "BDT",
  }).format(value);

export default function CartPage() {
  const {
    items,
    updateQuantity,
    removeItem,
    subtotal,
    clearCart,
  } = useCart();

  return (
    <main className="min-h-screen bg-slate-50 px-5 py-10 text-slate-900">
      <div className="mx-auto max-w-5xl">
        <Link href="/" className="text-sm font-semibold text-indigo-600">
          ← Continue Shopping
        </Link>

        <div className="mt-8 flex items-center justify-between">
          <h1 className="text-3xl font-extrabold">
            Shopping Cart
          </h1>

          {items.length > 0 && (
            <button
              onClick={clearCart}
              className="text-sm text-rose-600 hover:underline"
            >
              Clear Cart
            </button>
          )}
        </div>

        {items.length === 0 ? (
          <div className="mt-10 rounded-2xl border border-slate-200 bg-white p-12 text-center">
            <h2 className="text-xl font-bold">
              Your cart is empty
            </h2>
            <p className="mt-3 text-slate-500">
              Explore the collection and add your favorites.
            </p>
            <Link
              href="/"
              className="mt-7 inline-block rounded-xl bg-indigo-600 px-6 py-3 font-semibold text-white"
            >
              Browse Products
            </Link>
          </div>
        ) : (
          <div className="mt-8 grid gap-7 lg:grid-cols-[1fr_320px]">
            <div className="space-y-4">
              {items.map(({ product, quantity }) => (
                <div
                  key={product.id}
                  className="rounded-2xl border border-slate-200 bg-white p-6"
                >
                  <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                      <Link
                        href={`/products/${product.id}`}
                        className="text-lg font-bold hover:text-indigo-600"
                      >
                        {product.name}
                      </Link>

                      <p className="mt-1 text-xs text-slate-500">
                        {product.sku}
                      </p>

                      <p className="mt-3 font-bold text-indigo-700">
                        {formatPrice(Number(product.price))}
                      </p>
                    </div>

                    <button
                      onClick={() => removeItem(product.id)}
                      className="text-sm text-rose-600 hover:underline"
                    >
                      Remove
                    </button>
                  </div>

                  <div className="mt-5 flex items-center justify-between border-t border-slate-100 pt-4">
                    <div className="flex items-center gap-4">
                      <button
                        aria-label="Decrease quantity"
                        onClick={() =>
                          updateQuantity(product.id, quantity - 1)
                        }
                        disabled={quantity <= 1}
                        className="rounded-lg border px-3 py-1 disabled:opacity-40"
                      >
                        −
                      </button>

                      <span className="font-bold">{quantity}</span>

                      <button
                        aria-label="Increase quantity"
                        onClick={() =>
                          updateQuantity(product.id, quantity + 1)
                        }
                        disabled={
                          quantity >=
                          (product.inventory?.quantity ?? 0)
                        }
                        className="rounded-lg border px-3 py-1 disabled:opacity-40"
                      >
                        +
                      </button>
                    </div>

                    <span className="font-bold">
                      {formatPrice(
                        Number(product.price) * quantity
                      )}
                    </span>
                  </div>
                </div>
              ))}
            </div>

            <aside className="h-fit rounded-2xl border border-slate-200 bg-white p-7">
              <h2 className="text-xl font-extrabold">
                Order Summary
              </h2>

              <div className="mt-6 flex justify-between border-b pb-5 text-sm">
                <span className="text-slate-500">Subtotal</span>
                <span className="font-bold">
                  {formatPrice(subtotal)}
                </span>
              </div>

              <p className="mt-5 text-xs leading-5 text-slate-500">
                Shipping charges and final stock availability
                will be confirmed during checkout.
              </p>

            <Link
                href="/checkout"
                className="mt-6 block rounded-xl bg-indigo-600 px-5 py-4 text-center text-sm font-bold text-white transition hover:bg-indigo-700"
                >
                Proceed to Checkout →
            </Link>
            </aside>
          </div>
        )}
      </div>
    </main>
  );
}
