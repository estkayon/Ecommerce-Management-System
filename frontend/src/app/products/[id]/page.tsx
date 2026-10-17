
import Link from "next/link";
import { notFound } from "next/navigation";
import AddToCartButton from "@/components/add-to-cart-button";
import type { CartProduct } from "@/components/cart-provider";

export const dynamic = "force-dynamic";

type Product = CartProduct & {
  description: string | null;
  is_active: boolean;
};

export default async function ProductDetails({
  params,
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = await params;

  if (!/^\d+$/.test(id)) notFound();

  const baseUrl =
    process.env.LARAVEL_API_URL ??
    "http://127.0.0.1:8000/api";

  let product: Product;

  try {
    const response = await fetch(
      `${baseUrl}/products/${id}`,
      { cache: "no-store" }
    );

    if (response.status === 404) notFound();

    if (!response.ok) {
      throw new Error("Unable to fetch product.");
    }

    const payload: { data: Product } =
      await response.json();

    product = payload.data;
  } catch (error) {
    if (
      error instanceof Error &&
      error.message === "NEXT_HTTP_ERROR_FALLBACK;404"
    ) {
      throw error;
    }

    throw new Error("Product service is unavailable.");
  }

  const price = new Intl.NumberFormat("en-BD", {
    style: "currency",
    currency: "BDT",
  }).format(Number(product.price));

  return (
    <main className="min-h-screen bg-slate-50 px-5 py-10 text-slate-900">
      <div className="mx-auto max-w-6xl">
        <nav className="mb-8 flex gap-5 text-sm">
          <Link href="/" className="text-indigo-600 hover:underline">
            ← Back to Products
          </Link>
          <Link href="/cart" className="text-indigo-600 hover:underline">
            View Cart
          </Link>
        </nav>

        <div className="grid overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm md:grid-cols-2">
          <div className="flex min-h-80 items-center justify-center bg-gradient-to-br from-indigo-50 to-slate-200">
            <span className="text-8xl font-black tracking-tight text-indigo-300">
              {product.name
                .split(" ")
                .slice(0, 2)
                .map((word) => word[0])
                .join("")
                .toUpperCase()}
            </span>
          </div>

          <div className="flex flex-col justify-center p-8 md:p-12">
            <p className="text-xs font-bold tracking-widest text-indigo-600 uppercase">
              {product.sku}
            </p>

            <h1 className="mt-3 text-3xl font-extrabold md:text-4xl">
              {product.name}
            </h1>

            <p className="mt-5 text-3xl font-bold text-indigo-700">
              {price}
            </p>

            <p className="mt-5 leading-7 text-slate-600">
              {product.description ||
                "Discover this product from our collection."}
            </p>

            <p className="my-6 text-sm font-medium text-slate-500">
              Available stock: {product.inventory?.quantity ?? 0}
            </p>

            <div className="max-w-sm">
              <AddToCartButton product={product} />
            </div>
          </div>
        </div>
      </div>
    </main>
  );
}
