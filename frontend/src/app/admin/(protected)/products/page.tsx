
"use client";

import { useEffect, useState, type FormEvent } from "react";
import Link from "next/link";

type Product = {
  id: number;
  name: string;
  sku: string;
  description: string | null;
  price: string | number;
  is_active: boolean;
  inventory?: {
    quantity: number;
    reserved_quantity?: number;
  } | null;
};

type ProductResponse = {
  data: Product[];
  current_page: number;
  last_page: number;
  total: number;
  from: number | null;
  to: number | null;
};

type ProductForm = {
  name: string;
  sku: string;
  description: string;
  price: string;
  is_active: boolean;
};

const initialForm: ProductForm = {
  name: "",
  sku: "",
  description: "",
  price: "",
  is_active: true,
};

function formatPrice(value: string | number) {
  return new Intl.NumberFormat("en-BD", {
    style: "currency",
    currency: "BDT",
    maximumFractionDigits: 2,
  }).format(Number(value));
}

async function readResult(response: Response) {
  const result = await response.json();

  if (!response.ok) {
    const errors = result.errors as
      | Record<string, string[]>
      | undefined;

    const firstError = errors
      ? Object.values(errors).flat()[0]
      : undefined;

    throw new Error(
      firstError ?? result.message ?? "Request failed."
    );
  }

  return result;
}

export default function AdminProductsPage() {
  const [products, setProducts] = useState<Product[]>([]);
  const [searchInput, setSearchInput] = useState("");
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [pagination, setPagination] =
    useState<ProductResponse | null>(null);

  const [loading, setLoading] = useState(true);
  const [refreshKey, setRefreshKey] = useState(0);
  const [loadError, setLoadError] = useState("");

  const [editing, setEditing] = useState<Product | null>(null);
  const [formOpen, setFormOpen] = useState(false);
  const [form, setForm] = useState<ProductForm>(initialForm);
  const [saving, setSaving] = useState(false);
  const [formError, setFormError] = useState("");
  const [deletingId, setDeletingId] = useState<number | null>(
    null
  );
  const [notice, setNotice] = useState("");

  useEffect(() => {
    const controller = new AbortController();

    async function loadProducts() {
      setLoading(true);
      setLoadError("");

      try {
        const params = new URLSearchParams({
          page: String(page),
        });

        if (search) {
          params.set("search", search);
        }

        const response = await fetch(
          `/api/admin/products?${params.toString()}`,
          {
            cache: "no-store",
            signal: controller.signal,
          }
        );

        const result = (await readResult(
          response
        )) as ProductResponse;

        if (controller.signal.aborted) return;

        setProducts(result.data);
        setPagination(result);
      } catch (error) {
        if (controller.signal.aborted) return;

        setLoadError(
          error instanceof Error
            ? error.message
            : "Unable to load products."
        );
      } finally {
        if (!controller.signal.aborted) {
          setLoading(false);
        }
      }
    }

    void loadProducts();

    return () => controller.abort();
  }, [page, search, refreshKey]);

  function refreshProducts() {
    setRefreshKey((value) => value + 1);
  }

  function openCreate() {
    setEditing(null);
    setForm(initialForm);
    setFormError("");
    setFormOpen(true);
  }

  function openEdit(product: Product) {
    setEditing(product);
    setForm({
      name: product.name,
      sku: product.sku,
      description: product.description ?? "",
      price: String(product.price),
      is_active: Boolean(product.is_active),
    });
    setFormError("");
    setFormOpen(true);
  }

  function updateForm<K extends keyof ProductForm>(
    key: K,
    value: ProductForm[K]
  ) {
    setForm((current) => ({
      ...current,
      [key]: value,
    }));
  }

  async function saveProduct(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (saving) return;

    setSaving(true);
    setFormError("");
    setNotice("");

    try {
      if (
        !/^\d+(\.\d{1,2})?$/.test(form.price.trim()) ||
        !Number.isFinite(Number(form.price))
      ) {
        throw new Error(
          "Enter a valid price with at most two decimal places."
        );
      }

      const payload = {
        name: form.name.trim(),
        sku: form.sku.trim(),
        description: form.description.trim() || null,
        price: form.price.trim(),
        is_active: form.is_active,
      };

      const response = await fetch(
        editing
          ? `/api/admin/products/${editing.id}`
          : "/api/admin/products",
        {
          method: editing ? "PATCH" : "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify(payload),
        }
      );

      await readResult(response);

      setFormOpen(false);
      setNotice(
        editing
          ? "Product updated successfully."
          : "Product created successfully."
      );

      if (!editing) {
        setPage(1);
        setSearch("");
        setSearchInput("");
      }

      refreshProducts();
    } catch (error) {
      setFormError(
        error instanceof Error
          ? error.message
          : "Unable to save product."
      );
    } finally {
      setSaving(false);
    }
  }

  async function deleteProduct(product: Product) {
    const confirmed = window.confirm(
      `Delete "${product.name}"?\n\nThis will remove the product from the store.`
    );

    if (!confirmed) return;

    setDeletingId(product.id);
    setNotice("");
    setLoadError("");

    try {
      const response = await fetch(
        `/api/admin/products/${product.id}`,
        {
          method: "DELETE",
        }
      );

      await readResult(response);

      setNotice("Product deleted successfully.");

      if (products.length === 1 && page > 1) {
        setPage((value) => value - 1);
      } else {
        refreshProducts();
      }
    } catch (error) {
      setLoadError(
        error instanceof Error
          ? error.message
          : "Unable to delete product."
      );
    } finally {
      setDeletingId(null);
    }
  }

  function handleSearch(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setPage(1);
    setSearch(searchInput.trim());
  }

  return (
    <div className="space-y-7">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-xs font-bold tracking-[0.18em] text-indigo-600 uppercase">
            Store Catalog
          </p>

          <h2 className="mt-2 text-3xl font-black tracking-tight">
            Product Management
          </h2>

          <p className="mt-3 text-sm text-slate-500">
            Manage your product catalog, pricing and availability.
          </p>
        </div>

        <button
          onClick={openCreate}
          className="rounded-xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-indigo-700"
        >
          + Add New Product
        </button>
      </div>

      {notice && (
        <div
          role="status"
          className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800"
        >
          {notice}
        </div>
      )}

      {loadError && (
        <div
          role="alert"
          className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"
        >
          {loadError}
          <button
            onClick={refreshProducts}
            className="ml-3 font-bold underline"
          >
            Retry
          </button>
        </div>
      )}

      <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div className="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 p-5">
          <div>
            <h3 className="font-extrabold">
              All Products
            </h3>
            <p className="mt-1 text-xs text-slate-500">
              {pagination
                ? `${pagination.total} products found`
                : "Loading catalog"}
            </p>
          </div>

          <form
            onSubmit={handleSearch}
            className="flex w-full gap-2 sm:w-auto"
          >
            <input
              value={searchInput}
              onChange={(event) =>
                setSearchInput(event.target.value)
              }
              placeholder="Search name or SKU"
              aria-label="Search products"
              className="min-w-0 flex-1 rounded-xl border border-slate-200 px-4 py-2.5 text-sm outline-none focus:border-indigo-500 sm:w-56"
            />

            <button
              type="submit"
              className="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-slate-700"
            >
              Search
            </button>
          </form>
        </div>

        <div className="overflow-x-auto">
          <table className="w-full min-w-[780px] text-left text-sm">
            <thead className="bg-slate-50 text-xs font-bold text-slate-500 uppercase">
              <tr>
                <th className="px-6 py-4">Product</th>
                <th className="px-6 py-4">SKU</th>
                <th className="px-6 py-4">Price</th>
                <th className="px-6 py-4">Stock</th>
                <th className="px-6 py-4">Status</th>
                <th className="px-6 py-4 text-right">Actions</th>
              </tr>
            </thead>

            <tbody className="divide-y divide-slate-100">
              {loading ? (
                <tr>
                  <td
                    colSpan={6}
                    className="px-6 py-16 text-center text-slate-500"
                  >
                    Loading products...
                  </td>
                </tr>
              ) : products.length === 0 ? (
                <tr>
                  <td
                    colSpan={6}
                    className="px-6 py-16 text-center text-slate-500"
                  >
                    No products found.
                  </td>
                </tr>
              ) : (
                products.map((product) => {
                  const quantity =
                    product.inventory?.quantity;

                  return (
                    <tr
                      key={product.id}
                      className="transition hover:bg-slate-50/70"
                    >
                      <td className="px-6 py-5">
                        <p className="max-w-[240px] font-bold text-slate-900">
                          {product.name}
                        </p>
                        <p className="mt-1 text-xs text-slate-400">
                          ID #{product.id}
                        </p>
                      </td>

                      <td className="px-6 py-5 font-mono text-xs text-slate-600">
                        {product.sku}
                      </td>

                      <td className="px-6 py-5 font-bold">
                        {formatPrice(product.price)}
                      </td>

                      <td className="px-6 py-5">
                        <span
                          className={`font-bold ${
                            typeof quantity === "number" &&
                            quantity === 0
                              ? "text-rose-600"
                              : "text-slate-700"
                          }`}
                        >
                          {typeof quantity === "number"
                            ? quantity
                            : "—"}
                        </span>
                      </td>

                      <td className="px-6 py-5">
                        <span
                          className={`rounded-full px-3 py-1.5 text-xs font-bold ${
                            product.is_active
                              ? "bg-emerald-50 text-emerald-700"
                              : "bg-slate-100 text-slate-500"
                          }`}
                        >
                          {product.is_active
                            ? "Active"
                            : "Inactive"}
                        </span>
                      </td>

                      <td className="px-6 py-5">
                        <div className="flex justify-end gap-3">
                            <Link
                                href={`/admin/products/${product.id}/inventory`}
                                className="font-bold text-emerald-600 hover:text-emerald-800"
                            >
                                Stock
                            </Link>
                          <button
                            onClick={() => openEdit(product)}
                            className="font-bold text-indigo-600 hover:text-indigo-800"
                          >
                            Edit
                          </button>

                          <button
                            onClick={() =>
                              void deleteProduct(product)
                            }
                            disabled={deletingId !== null}
                            className="font-bold text-rose-600 hover:text-rose-800 disabled:opacity-50"
                          >
                            {deletingId === product.id
                              ? "Deleting..."
                              : "Delete"}
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>

        {pagination && pagination.last_page > 1 && (
          <div className="flex items-center justify-between gap-4 border-t border-slate-100 px-6 py-5">
            <p className="text-xs text-slate-500">
              Page {pagination.current_page} of{" "}
              {pagination.last_page}
            </p>

            <div className="flex gap-2">
              <button
                disabled={loading || page <= 1}
                onClick={() =>
                  setPage((value) => Math.max(1, value - 1))
                }
                className="rounded-lg border border-slate-200 px-4 py-2 text-xs font-bold disabled:opacity-40"
              >
                Previous
              </button>

              <button
                disabled={
                  loading || page >= pagination.last_page
                }
                onClick={() =>
                  setPage((value) => value + 1)
                }
                className="rounded-lg border border-slate-200 px-4 py-2 text-xs font-bold disabled:opacity-40"
              >
                Next
              </button>
            </div>
          </div>
        )}
      </section>

      {formOpen && (
        <div className="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-950/60 p-4 backdrop-blur-sm">
          <div
            role="dialog"
            aria-modal="true"
            aria-labelledby="product-form-title"
            className="my-auto w-full max-w-xl rounded-3xl bg-white p-7 shadow-2xl sm:p-9"
          >
            <div className="flex items-start justify-between gap-4">
              <div>
                <p className="text-xs font-bold tracking-wider text-indigo-600 uppercase">
                  Product Editor
                </p>

                <h3
                  id="product-form-title"
                  className="mt-2 text-2xl font-black"
                >
                  {editing ? "Edit Product" : "Add New Product"}
                </h3>
              </div>

              <button
                type="button"
                disabled={saving}
                onClick={() => setFormOpen(false)}
                aria-label="Close product form"
                className="rounded-xl bg-slate-100 px-3 py-2 font-bold text-slate-600"
              >
                ✕
              </button>
            </div>

            <form
              onSubmit={(event) => void saveProduct(event)}
              className="mt-7 space-y-5"
            >
              <div className="grid gap-5 sm:grid-cols-2">
                <div>
                  <label
                    htmlFor="product-name"
                    className="mb-2 block text-sm font-bold"
                  >
                    Product Name
                  </label>

                  <input
                    id="product-name"
                    required
                    maxLength={255}
                    value={form.name}
                    onChange={(event) =>
                      updateForm("name", event.target.value)
                    }
                    className="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-indigo-500"
                  />
                </div>

                <div>
                  <label
                    htmlFor="product-sku"
                    className="mb-2 block text-sm font-bold"
                  >
                    SKU
                  </label>

                  <input
                    id="product-sku"
                    required
                    maxLength={100}
                    value={form.sku}
                    onChange={(event) =>
                      updateForm("sku", event.target.value)
                    }
                    className="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-indigo-500"
                  />
                </div>
              </div>

              <div>
                <label
                  htmlFor="product-description"
                  className="mb-2 block text-sm font-bold"
                >
                  Description
                </label>

                <textarea
                  id="product-description"
                  rows={3}
                  value={form.description}
                  onChange={(event) =>
                    updateForm(
                      "description",
                      event.target.value
                    )
                  }
                  className="w-full resize-none rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-indigo-500"
                />
              </div>

              <div>
                <label
                  htmlFor="product-price"
                  className="mb-2 block text-sm font-bold"
                >
                  Price (BDT)
                </label>

                <input
                  id="product-price"
                  type="number"
                  min="0"
                  step="0.01"
                  required
                  value={form.price}
                  onChange={(event) =>
                    updateForm("price", event.target.value)
                  }
                  className="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-indigo-500"
                />
              </div>

              <label className="flex cursor-pointer items-center gap-3 rounded-xl bg-slate-50 p-4">
                <input
                  type="checkbox"
                  checked={form.is_active}
                  onChange={(event) =>
                    updateForm(
                      "is_active",
                      event.target.checked
                    )
                  }
                  className="h-4 w-4 accent-indigo-600"
                />

                <span className="text-sm font-semibold">
                  Active Product
                </span>
              </label>

              {formError && (
                <p
                  role="alert"
                  className="rounded-xl bg-rose-50 p-4 text-sm text-rose-700"
                >
                  {formError}
                </p>
              )}

              <div className="flex justify-end gap-3 pt-3">
                <button
                  type="button"
                  disabled={saving}
                  onClick={() => setFormOpen(false)}
                  className="rounded-xl border border-slate-200 px-5 py-3 text-sm font-bold disabled:opacity-50"
                >
                  Cancel
                </button>

                <button
                  type="submit"
                  disabled={saving}
                  className="rounded-xl bg-indigo-600 px-6 py-3 text-sm font-bold text-white hover:bg-indigo-700 disabled:opacity-50"
                >
                  {saving
                    ? "Saving..."
                    : editing
                      ? "Save Changes"
                      : "Create Product"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
