import { computed, ref, onMounted } from "vue";
import { createResource } from "frappe-ui";

function safeParseJson(value: string | null | undefined, fallback: any = null) {
  if (!value) return fallback;
  try {
    return JSON.parse(value);
  } catch {
    return fallback;
  }
}

export function useFormSchema(formName: string) {
  console.log("[useFormSchema] Initializing for formName:", formName);

  // Add alternative fetch for debugging
  const debugFormDef = ref<any>(null);

  onMounted(async () => {
    console.log("[useFormSchema] Trying direct fetch for:", formName);
    try {
      const url = `/api/resource/NCE Form Definition/${encodeURIComponent(formName)}`;
      console.log("[useFormSchema] Fetching URL:", url);
      const response = await fetch(url, {
        credentials: "include",
        headers: {
          Accept: "application/json",
        },
      });
      console.log("[useFormSchema] Fetch response status:", response.status);

      if (response.ok) {
        const data = await response.json();
        console.log("[useFormSchema] Direct fetch success:", data);
        debugFormDef.value = data.data;
      } else {
        const text = await response.text();
        console.error(
          "[useFormSchema] Direct fetch failed:",
          response.status,
          text,
        );
      }
    } catch (error) {
      console.error("[useFormSchema] Direct fetch error:", error);
    }

    // Also try the list API directly
    try {
      const listUrl = `/api/resource/NCE Form Definition?filters=${encodeURIComponent(
        JSON.stringify([
          ["name", "=", formName],
          ["enabled", "=", 1],
        ]),
      )}&fields=["*"]&limit_page_length=1`;
      console.log("[useFormSchema] Trying list URL:", listUrl);
      const listResponse = await fetch(listUrl, { credentials: "include" });
      if (listResponse.ok) {
        const listData = await listResponse.json();
        console.log("[useFormSchema] List fetch result:", listData);
      }
    } catch (error) {
      console.error("[useFormSchema] List fetch error:", error);
    }
  });

  const resource = createResource({
    url: "frappe.client.get_list",
    params: {
      doctype: "NCE Form Definition",
      filters: { name: formName, enabled: 1 },
      fields: ["*"],
      limit_page_length: 1,
    },
    auto: true,
    onSuccess: (data: any) => {
      console.log("[useFormSchema] Success - data received:", data);
    },
    onError: (error: any) => {
      console.error("[useFormSchema] Error loading form:", error);
    },
  });

  const formDef = computed(() => {
    const list = resource.data as any[] | null;
    console.log("[useFormSchema] Computing formDef - raw list:", list);
    console.log("[useFormSchema] debugFormDef value:", debugFormDef.value);

    // Use debugFormDef if resource isn't working
    const result = list && list.length > 0 ? list[0] : debugFormDef.value;

    if (result) {
      console.log("[useFormSchema] FormDef found:", {
        name: result.name,
        title: result.title,
        target_doctype: result.target_doctype,
        has_grid_layout: !!result.grid_layout,
        has_grid_config: !!result.grid_config,
        enabled: result.enabled,
      });
    } else {
      console.log("[useFormSchema] No formDef found for:", formName);
      console.log("[useFormSchema] Resource state:", {
        loading: resource.loading.value,
        error: resource.error.value,
        data: resource.data,
      });
    }
    return result;
  });

  const schema = computed(() => safeParseJson(formDef.value?.form_schema, []));

  const tabLayout = computed(() => safeParseJson(formDef.value?.tab_layout));

  const fieldMapping = computed(() =>
    safeParseJson(formDef.value?.field_mapping),
  );

  const targetDoctype = computed(() => formDef.value?.target_doctype || "");

  const submitAction = computed(
    () => formDef.value?.on_submit_action || "save",
  );

  const customApiMethod = computed(
    () => formDef.value?.custom_api_method || "",
  );

  // Log initial state
  console.log("[useFormSchema] Initial state:", {
    loading: resource.loading.value,
    error: resource.error.value,
    formName: formName,
  });

  return {
    formDef,
    schema,
    tabLayout,
    fieldMapping,
    targetDoctype,
    submitAction,
    customApiMethod,
    loading: resource.loading,
    error: resource.error,
  };
}
