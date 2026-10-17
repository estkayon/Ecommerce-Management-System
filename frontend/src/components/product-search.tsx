
"use client";

import { useRouter } from "next/navigation";
import { useState, type FormEvent } from "react";
import { Search, X } from "lucide-react";

type Props = {
  initialSearch?: string;
};

export default function ProductSearch({
  initialSearch = "",
}: Props) {
  const router = useRouter();
  const [search, setSearch] = useState(initialSearch);

  function handleSearch(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    const query = search.trim();

    if (!query) {
      router.push("/#products");
      return;
    }

    router.push(
      `/?search=${encodeURIComponent(query)}#products`
    );
  }

  function clearSearch() {
    setSearch("");
    router.push("/#products");
  }

  return (
    <form
      onSubmit={handleSearch}
      role="search"
      className="w-full"
    >
      <div className="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white p-2 shadow-sm transition-all focus-within:border-indigo-400 focus-within:ring-4 focus-within:ring-indigo-100">
        <Search
          size={21}
          className="ml-3 shrink-0 text-slate-400"
        />

        <input
          type="search"
          name="search"
          aria-label="Search products"
          placeholder="Search products by name or SKU..."
          maxLength={100}
          value={search}
          onChange={(event) =>
            setSearch(event.target.value)
          }
          className="min-w-0 flex-1 bg-transparent px-2 py-3 text-sm text-slate-900 outline-none placeholder:text-slate-400 sm:text-base"
        />

        {initialSearch && (
          <button
            type="button"
            onClick={clearSearch}
            aria-label="Clear search"
            className="rounded-xl p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-900"
          >
            <X size={20} />
          </button>
        )}

        <button
          type="submit"
          className="shrink-0 rounded-xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white transition hover:bg-indigo-700 sm:px-7"
        >
          Search
        </button>
      </div>

      <p className="mt-3 text-xs text-slate-400">
        Search by product name or SKU
      </p>
    </form>
  );
}
