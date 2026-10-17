
import { adminApi } from "@/lib/admin-api";

export const runtime = "nodejs";

export async function GET() {
  return adminApi("/admin/dashboard");
}
