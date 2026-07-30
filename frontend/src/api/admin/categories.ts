import { apiClient } from "../client";
import type { ApiResponse, Category, PaginatedData } from "@/types";

export interface ListAdminCategoriesParams {
    q?: string;
    sort?: string;
    page?: number;
    per_page?: number;
}

export async function listAdminCategories(
    params: ListAdminCategoriesParams = {},
): Promise<PaginatedData<Category>> {
    const { data } = await apiClient.get<ApiResponse<PaginatedData<Category>>>(
        "/admin/categories",
        {
            params,
        },
    );
    return data.data;
}

export interface CategoryInput {
    name: string;
    description?: string | null;
    is_active?: boolean;
}

export async function createCategory(input: CategoryInput): Promise<Category> {
    const { data } = await apiClient.post<ApiResponse<Category>>(
        "/admin/categories",
        input,
    );
    return data.data;
}

export async function updateCategory(
    slug: string,
    input: Partial<CategoryInput>,
): Promise<Category> {
    const { data } = await apiClient.put<ApiResponse<Category>>(
        `/admin/categories/${slug}`,
        input,
    );
    return data.data;
}

export async function deleteCategory(slug: string): Promise<void> {
    await apiClient.delete(`/admin/categories/${slug}`);
}
