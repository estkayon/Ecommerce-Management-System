
"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import {
  useCallback,
  useEffect,
  useState,
  type FormEvent,
} from "react";

type Inventory = {
  id?: number;
  quantity: number;
  reserved_quantity?: number;
  available_quantity?: number;
};

type Movement = {
  id: number;
  quantity_change?: number;
  change?: number;
  reason?: string | null;
  created_at?: string;
};

type PaginatedMovements = {
  data: Movement[];
  current_page: number;
  last_page: number;
  total: number;
};

type InventoryResponse = {
  data: {
    product: {
      id: number;
      name: string;
      sku: string;
    };
    inventory: Inventory | null;
    movements: PaginatedMovements;
  };
};

type AdjustmentMode = "in" | "out";

async function parseResponse(response: Response) {
  const result = await response.json();

  if (!response.ok) {
    const errors = result.errors as
      | Record<string, string[]>
      | undefined;

    throw new Error(
      (errors && Object.values(errors).flat()[0]) ||
        result.message ||
        "Request failed."
    );
  }

  return result;
}

export default function InventoryManagementPage() {
  const params = useParams<{ id: string }>();
  const productId = params.id;

  const [data, setData] = useState<
    InventoryResponse["data"] | null
  >(null);
  const [page, setPage] = useState(1);
  const [refreshKey, setRefreshKey] = useState(0);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const [formError, setFormError] = useState("");
  const [notice, setNotice] = useState("");

  const [mode, setMode] = useState<AdjustmentMode>("in");
  const [quantity, setQuantity] = useState("");
  const [reason, setReason] = useState("");

  const loadInventory = useCallback(async (signal: AbortSignal) => {
    setLoading(true);
    setError("");

    try {
      const response = await fetch(
        `/api/admin/products/${productId}/inventory?page=${page}`,
        { cache: "no-store", signal }
      );

      const result = (await parseResponse(
        response
      )) as InventoryResponse;

      if (!signal.aborted) {
        setData(result.data);
      }
    } catch (caught) {
      if (!signal.aborted) {
        setError(
          caught instanceof Error
            ? caught.message
            : "Unable to load inventory."
        );
      }
    } finally {
      if (!signal.aborted) {
        setLoading(false);
      }
    }
  }, [productId, page]);

  useEffect(() => {
    const controller = new AbortController();

    void loadInventory(controller.signal);

    return () => controller.abort();
  }, [loadInventory, refreshKey]);

  async function handleAdjustment(
    event: FormEvent<HTMLFormElement>
  ) {
    event.preventDefault();

    if (saving) return;

    setFormError("");
    setNotice("");

    const parsedQuantity = Number(quantity);

    if (
      !Number.isSafeInteger(parsedQuantity) ||
      parsedQuantity < 1 ||
      parsedQuantity > 1000000
    ) {
      setFormError(
        "Quantity must be a whole number between 1 and 1,000,000."
      );
      return;
    }

    if (!reason.trim() || reason.trim().length > 255) {
      setFormError(
        "Reason is required and must be within 255 characters."
      );
      return;
    }

    const quantityChange =
      mode === "in" ? parsedQuantity : -parsedQuantity;

    if (
      mode === "out" &&
      data?.inventory &&
      quantityChange < 0
    ) {
      const stock = Number(data.inventory.quantity);
      const reserved = Number(
        data.inventory.reserved_quantity ?? 0
      );

      if (parsedQuantity > stock - reserved) {
        setFormError(
          "Stock Out exceeds available stock."
        );
        return;
      }
    }

    setSaving(true);

    try {
      const response = await fetch(
        `/api/admin/products/${productId}/inventory`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({
            quantity_change: quantityChange,
            reason: reason.trim(),
          }),
        }
      );

      await parseResponse(response);

      setNotice(
        mode === "in"
          ? "Stock added successfully."
          : "Stock removed successfully."
      );

      setQuantity("");
      setReason("");
      setPage(1);
      setRefreshKey((value) => value + 1);
    } catch (caught) {
      setFormError(
        caught instanceof Error
          ? caught.message
          : "Unable to update stock."
      );
    } finally {
      setSaving(false);
    }
  }

  const inventory = data?.inventory;

  const totalStock =
    inventory ? Number(inventory.quantity) : null;

  const reservedStock =
    inventory?.reserved_quantity !== undefined
      ? Number(inventory.reserved_quantity)
      : null;

  const availableStock =
    inventory?.available_quantity !== undefined
      ? Number(inventory.available_quantity)
      : totalStock !== null && reservedStock !== null
        ? totalStock - reservedStock
        : null;

  const movements = data?.movements;

  function formatDate(value?: string) {
    if (!value) return "—";

    const date = new Date(value);

    return Number.isNaN(date.getTime())
      ? value
      : date.toLocaleString("en-BD");
  }

  return (
    <div className="space-y-8">
      <div>
        <Link
          href="/admin/products"
          className="text-sm font-semibold text-indigo-600 hover:text-indigo-800"
        >
          ← Back to Products
        </Link>

        <p className="mt-6 text-xs font-bold tracking-[0.18em] text-indigo-600 uppercase">
          Inventory Control
        </p>

        <h2 className="mt-2 text-3xl font-black tracking-tight">
          {data?.product.name ?? "Product Inventory"}
        </h2>

        <p className="mt-2 text-sm text-slate-500">
          SKU: {data?.product.sku ?? "Loading..."}
        </p>
      </div>

      {error && (
        <div
          role="alert"
          className="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700"
        >
          {error}
          <button
            type="button"
            onClick={() =>
              setRefreshKey((value) => value + 1)
            }
            className="ml-3 font-bold underline"
          >
            Retry
          </button>
        </div>
      )}

      {notice && (
        <div
          role="status"
          className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-bold text-emerald-700"
        >
          {notice}
        </div>
      )}

      <section className="grid gap-4 sm:grid-cols-3">
        {[
          { label: "Total Stock", value: totalStock },
          { label: "Reserved Stock", value: reservedStock },
          { label: "Available Stock", value: availableStock },
        ].map((item) => (
          <div
            key={item.label}
            className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
          >
            <p className="text-sm font-medium text-slate-500">
              {item.label}
            </p>

            <p className="mt-3 text-4xl font-black tracking-tight">
              {loading ? "…" : item.value ?? "—"}
            </p>
          </div>
        ))}
      </section>

      <section className="grid gap-6 lg:grid-cols-[0.95fr_1.4fr]">
        <div className="h-fit rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
          <h3 className="text-xl font-black">
            Adjust Stock
          </h3>

          <p className="mt-2 text-sm text-slate-500">
            All adjustments are recorded in inventory history.
          </p>

          <div className="mt-7 grid grid-cols-2 gap-2 rounded-xl bg-slate-100 p-1.5">
            <button
              type="button"
              onClick={() => setMode("in")}
              className={`rounded-lg px-4 py-3 text-sm font-bold transition ${
                mode === "in"
                  ? "bg-white text-emerald-700 shadow-sm"
                  : "text-slate-500"
              }`}
            >
              + Stock In
            </button>

            <button
              type="button"
              onClick={() => setMode("out")}
              className={`rounded-lg px-4 py-3 text-sm font-bold transition ${
                mode === "out"
                  ? "bg-white text-rose-700 shadow-sm"
                  : "text-slate-500"
              }`}
            >
              − Stock Out
            </button>
          </div>

          <form
            onSubmit={(event) => void handleAdjustment(event)}
            className="mt-7 space-y-5"
          >
            <div>
              <label
                htmlFor="stock-quantity"
                className="mb-2 block text-sm font-bold"
              >
                Quantity
              </label>

              <input
                id="stock-quantity"
                type="number"
                min="1"
                max="1000000"
                step="1"
                required
                value={quantity}
                onChange={(event) =>
                  setQuantity(event.target.value)
                }
                placeholder="Enter quantity"
                className="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-indigo-500"
              />
            </div>

            <div>
              <label
                htmlFor="stock-reason"
                className="mb-2 block text-sm font-bold"
              >
                Reason
              </label>

              <textarea
                id="stock-reason"
                required
                maxLength={255}
                rows={3}
                value={reason}
                onChange={(event) =>
                  setReason(event.target.value)
                }
                placeholder={
                  mode === "in"
                    ? "Example: New supplier shipment"
                    : "Example: Damaged items removed"
                }
                className="w-full resize-none rounded-xl border border-slate-200 px-4 py-3 text-sm outline-none focus:border-indigo-500"
              />
            </div>

            {formError && (
              <div
                role="alert"
                className="rounded-xl bg-rose-50 p-4 text-sm text-rose-700"
              >
                {formError}
              </div>
            )}

            <button
              type="submit"
              disabled={saving || loading || !inventory}
              className={`w-full rounded-xl px-5 py-3.5 text-sm font-bold text-white transition disabled:opacity-50 ${
                mode === "in"
                  ? "bg-emerald-600 hover:bg-emerald-700"
                  : "bg-rose-600 hover:bg-rose-700"
              }`}
            >
              {saving
                ? "Updating Inventory..."
                : mode === "in"
                  ? "Confirm Stock In"
                  : "Confirm Stock Out"}
            </button>
          </form>
        </div>

        <div className="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
          <div className="border-b border-slate-100 p-6">
            <h3 className="text-xl font-black">
              Inventory Movement History
            </h3>
            <p className="mt-2 text-sm text-slate-500">
              Recent stock adjustments and their reasons.
            </p>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full min-w-[460px] text-left text-sm">
              <thead className="bg-slate-50 text-xs font-bold text-slate-500 uppercase">
                <tr>
                  <th className="px-5 py-4">Change</th>
                  <th className="px-5 py-4">Reason</th>
                  <th className="px-5 py-4">Date</th>
                </tr>
              </thead>

              <tbody className="divide-y divide-slate-100">
                {loading ? (
                  <tr>
                    <td
                      colSpan={3}
                      className="px-5 py-12 text-center text-slate-500"
                    >
                      Loading history...
                    </td>
                  </tr>
                ) : !movements?.data.length ? (
                  <tr>
                    <td
                      colSpan={3}
                      className="px-5 py-12 text-center text-slate-500"
                    >
                      No inventory movements found.
                    </td>
                  </tr>
                ) : (
                  movements.data.map((movement) => {
                    const change =
                      movement.quantity_change ??
                      movement.change ??
                      null;

                    return (
                      <tr key={movement.id}>
                        <td className="px-5 py-5">
                          <span
                            className={`font-extrabold ${
                              change === null
                                ? "text-slate-500"
                                : change > 0
                                  ? "text-emerald-600"
                                  : "text-rose-600"
                            }`}
                          >
                            {change === null
                              ? "—"
                              : change > 0
                                ? `+${change}`
                                : change}
                          </span>
                        </td>

                        <td className="max-w-[260px] px-5 py-5 text-slate-700">
                          {movement.reason || "—"}
                        </td>

                        <td className="px-5 py-5 text-xs text-slate-500">
                          {formatDate(movement.created_at)}
                        </td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>

          {movements && movements.last_page > 1 && (
            <div className="flex items-center justify-between border-t border-slate-100 p-5">
              <span className="text-xs text-slate-500">
                Page {movements.current_page} of{" "}
                {movements.last_page}
              </span>

              <div className="flex gap-2">
                <button
                  disabled={loading || page <= 1}
                  onClick={() =>
                    setPage((value) => Math.max(1, value - 1))
                  }
                  className="rounded-lg border px-3 py-2 text-xs font-bold disabled:opacity-40"
                >
                  Previous
                </button>

                <button
                  disabled={
                    loading || page >= movements.last_page
                  }
                  onClick={() =>
                    setPage((value) => value + 1)
                  }
                  className="rounded-lg border px-3 py-2 text-xs font-bold disabled:opacity-40"
                >
                  Next
                </button>
              </div>
            </div>
          )}
        </div>
      </section>
    </div>
  );
}
