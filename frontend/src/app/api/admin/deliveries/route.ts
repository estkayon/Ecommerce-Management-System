
import { adminApi } from "@/lib/admin-api";

export const runtime = "nodejs";

export async function GET(request: Request) {
  const url = new URL(request.url);
  const params = new URLSearchParams();

  const page = Number(url.searchParams.get("page") ?? "1");
  const status = url.searchParams.get("status") ?? "";

  params.set(
    "page",
    String(Number.isSafeInteger(page) && page > 0 ? page : 1)
  );

  if (
    ["pending", "processing", "created", "dispatched", "delivered", "failed"]
      .includes(status)
  ) {
    params.set("status", status);
  }

  return adminApi(`/admin/deliveries?${params.toString()}`);
}
