
import type { Metadata } from "next";
import StorefrontHero from "@/components/storefront-hero";
import AnimatedProductCard from "@/components/animated-product-card";
import ProductSearch from "@/components/product-search";

export const metadata: Metadata = {
  title: "ShopSphere | Everyday Tech, Elevated",
  description:
    "Discover smart accessories and modern everyday essentials.",
};

type Product = {
  id: number;
  name: string;
  sku: string;
  description: string | null;
  price: string | number;
  is_active: boolean;
  inventory?: {
    quantity: number;
  } | null;
};

type ProductResponse = {
  data?: Product[];
  total?: number;
};

async function getProducts(search = ""): Promise<{  
  products: Product[];
  total: number;
  error: string | null;
}> {
  const baseUrl =
    process.env.LARAVEL_API_URL ??
    "http://127.0.0.1:8000/api";

  try {
    const params = new URLSearchParams({
        per_page: "100",
      });

      if (search) {
        params.set("search", search);
      }

      const response = await fetch(
        `${baseUrl}/products?${params.toString()}`,
        { next: { revalidate: 60 } }
      );

      if (!response.ok) {
        throw new Error("Product API unavailable");
      }

    const payload: ProductResponse | Product[] =
      await response.json();

    const products = Array.isArray(payload)
      ? payload
      : Array.isArray(payload.data)
        ? payload.data
        : [];

    return {
      products: products.filter(
        (product) => product.is_active !== false
      ),
      total: Array.isArray(payload)
        ? products.length
        : payload.total ?? products.length,
      error: null,
    };
  } catch {
    return {
      products: [],
      total: 0,
      error: "Unable to load products. Please check the Laravel backend.",
    };
  }
}

  type HomeProps = {
    searchParams: Promise<{
      search?: string | string[];
    }>;
  };

  export default async function Home({
    searchParams,
  }: HomeProps) {
    const params = await searchParams;

    const search =
      typeof params.search === "string"
        ? params.search.trim().slice(0, 100)
        : "";

    const { products, total, error } =
      await getProducts(search);

  return (
    <div className="min-h-screen bg-[#f8fafc] text-slate-900">
      <StorefrontHero />

      <main>
        <section
          id="benefits"
          className="border-b border-slate-200 bg-white"
        >
          <div className="mx-auto grid max-w-7xl gap-5 px-5 py-9 sm:grid-cols-3 sm:px-8">
            {[
              {
                icon: "✦",
                title: "Curated Essentials",
                description:
                  "Practical products for modern everyday life.",
              },
              {
                icon: "◎",
                title: "Easy Shopping",
                description:
                  "A simple and convenient online experience.",
              },
              {
                icon: "↗",
                title: "Order Management",
                description:
                  "Stay informed throughout your order journey.",
              },
            ].map((feature) => (
              <div
                key={feature.title}
                className="flex items-start gap-4 rounded-2xl p-4 transition-colors hover:bg-slate-50"
              >
                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-indigo-50 text-2xl text-indigo-600">
                  {feature.icon}
                </div>
                <div>
                  <h3 className="font-bold tracking-tight">
                    {feature.title}
                  </h3>
                  <p className="mt-1 text-sm leading-6 text-slate-500">
                    {feature.description}
                  </p>
                </div>
              </div>
            ))}
          </div>
        </section>

        <section
          id="products"
          className="mx-auto max-w-7xl px-5 py-20 sm:px-8 lg:py-24"
        >
          <div className="mb-12 flex flex-wrap items-end justify-between gap-5">
            <div>
              <span className="inline-block rounded-full bg-indigo-50 px-4 py-2 text-[11px] font-bold tracking-[0.18em] text-indigo-700 uppercase">
                The Collection
              </span>

              <h2 className="mt-5 text-4xl font-black tracking-[-0.04em] text-slate-950 sm:text-5xl">
                Find your next favorite.
              </h2>

              <p className="mt-4 max-w-xl text-sm leading-7 text-slate-500 sm:text-base">
                Explore modern accessories and everyday
                essentials selected for simplicity and utility.
              </p>
            </div>

            <span className="rounded-full border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-600 shadow-sm">
              {total} Products
            </span>
          </div>

          <div className="mb-10">
  <div className="mx-auto max-w-3xl">
    <ProductSearch
      key={search}
      initialSearch={search}
    />
  </div>

        {search && !error && (
          <div className="mt-7 flex flex-wrap items-center justify-between gap-3">
            <p className="text-sm text-slate-600">
              Found{" "}
              <span className="font-bold text-indigo-600">
                {total}
              </span>{" "}
              result(s) for{" "}
              <span className="font-bold text-slate-900">
                &quot;{search}&quot;
              </span>
            </p>

            <a
              href="/#products"
              className="text-sm font-bold text-indigo-600 hover:text-indigo-800"
            >
              Clear Search →
            </a>
          </div>
        )}
      </div>

          {error ? (
            <div
              role="alert"
              className="rounded-3xl border border-rose-200 bg-rose-50 p-10 text-center text-rose-700"
            >
              {error}
            </div>
          ) : products.length === 0 ? (
            <div className="rounded-3xl border border-slate-200 bg-white p-14 text-center">
              <h3 className="text-xl font-bold">
                {search
                  ? "No matching products found"
                  : "No products available"}
              </h3>
              <p className="mt-3 text-slate-500">
                {search
                  ? "Try another product name or SKU."
                  : "Please check again later."}
              </p>
            </div>
          ) : (
            <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
              {products.map((product, index) => (
                <AnimatedProductCard
                  key={product.id}
                  product={product}
                  index={index}
                />
              ))}
            </div>
          )}
        </section>

        <section className="bg-[#101a34] px-5 py-20 text-center text-white">
          <div className="mx-auto max-w-3xl">
            <span className="text-xs font-bold tracking-[0.2em] text-indigo-300 uppercase">
              Thoughtfully Selected
            </span>
            <h2 className="mt-5 text-3xl font-black tracking-tight sm:text-5xl">
              Good design belongs in everyday life.
            </h2>
            <p className="mx-auto mt-5 max-w-xl leading-7 text-slate-300">
              Discover practical products and shop with a
              simple, focused experience.
            </p>
            <a
              href="#products"
              className="mt-9 inline-block rounded-xl bg-white px-7 py-4 text-sm font-bold text-indigo-700 transition hover:bg-indigo-50"
            >
              Explore the Collection ↗
            </a>
          </div>
        </section>
      </main>

      <footer className="border-t border-slate-200 bg-white">
        <div className="mx-auto flex max-w-7xl flex-col justify-between gap-3 px-5 py-8 text-sm text-slate-500 sm:flex-row sm:px-8">
          <p className="text-base font-extrabold text-slate-950">
            ShopSphere.
          </p>
          <p>Single-Vendor E-commerce Platform</p>
        </div>
      </footer>
    </div>
  );
}
