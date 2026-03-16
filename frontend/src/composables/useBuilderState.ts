import { reactive, computed } from 'vue'

export interface ElementConfig {
  label: string
  placeholder: string
  fieldPath: string
  fieldType: string
  terminalDoctype: string
  editable: boolean
  frameColor: string
}

export interface BuilderElement {
  id: string
  type: 'field' | 'caption'
  x: number
  y: number
  w: number
  h: number
  config: ElementConfig
}

export interface GridConfig {
  cellSize: number
  gap: number
}

export interface BuilderState {
  formName: string
  title: string
  targetDoctype: string
  gridConfig: GridConfig
  elements: BuilderElement[]
  selectedId: string | null
}

export function useBuilderState(formName: string) {
  const state = reactive<BuilderState>({
    formName,
    title: '',
    targetDoctype: '',
    gridConfig: {
      cellSize: 10,
      gap: 1
    },
    elements: [],
    selectedId: null
  })

  const selectedElement = computed<BuilderElement | null>(() => {
    return state.elements.find(el => el.id === state.selectedId) ?? null
  })

  function addElement(type: 'field' | 'caption', x: number, y: number): void {
    const id = `el_${Date.now()}`
    const sel = selectedElement.value

    // Inherit visual config from selected element (not data binding)
    const baseConfig: ElementConfig = sel
      ? {
          ...sel.config,
          label: type === 'field' ? 'New Field' : 'Caption',
          fieldPath: '',
          fieldType: '',
          terminalDoctype: '',
        }
      : {
          label: type === 'field' ? 'New Field' : 'Caption',
          placeholder: '',
          fieldPath: '',
          fieldType: '',
          terminalDoctype: '',
          editable: true,
          frameColor: '',
        }

    const element: BuilderElement = {
      id,
      type,
      x,
      y,
      w: sel?.w ?? 25,
      h: sel?.h ?? 3,
      config: baseConfig,
    }
    state.elements.push(element)
    state.selectedId = id
  }

  function removeElement(id: string): void {
    const index = state.elements.findIndex(el => el.id === id)
    if (index !== -1) {
      state.elements.splice(index, 1)
      if (state.selectedId === id) {
        state.selectedId = null
      }
    }
  }

  function updateElement(id: string, changes: Partial<ElementConfig>): void {
    const element = state.elements.find(el => el.id === id)
    if (element) {
      Object.assign(element.config, changes)
    }
  }

  function moveElement(id: string, x: number, y: number): void {
    const element = state.elements.find(el => el.id === id)
    if (element) {
      element.x = Math.max(0, x)
      element.y = Math.max(0, y)
    }
  }

  function resizeElement(id: string, w: number, h: number): void {
    const element = state.elements.find(el => el.id === id)
    if (element) {
      element.w = Math.max(1, w)
      element.h = Math.max(1, h)
    }
  }

  function selectElement(id: string | null): void {
    state.selectedId = id
  }

  function serialise(): { grid_layout: string; grid_config: string } {
    return {
      grid_layout: JSON.stringify(state.elements),
      grid_config: JSON.stringify(state.gridConfig)
    }
  }

  function toSlug(text: string): string {
    return text.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')
  }

  async function save(): Promise<string> {
    const { grid_layout, grid_config } = serialise()

    const headers: Record<string, string> = {
      'Content-Type': 'application/json',
      'X-Frappe-CSRF-Token': (window as any).csrf_token
    }

    const isNew = state.formName === 'new' || !state.formName

    if (isNew) {
      if (!state.title) throw new Error('Title is required')
      if (!state.targetDoctype) throw new Error('Target DocType is required')

      const slug = toSlug(state.title) || `form-${Date.now()}`

      const res = await fetch('/api/resource/NCE Form Definition', {
        method: 'POST',
        headers,
        credentials: 'include',
        body: JSON.stringify({
          form_name: slug,
          title: state.title,
          target_doctype: state.targetDoctype,
          enabled: 1,
          grid_layout,
          grid_config,
        })
      })
      if (!res.ok) {
        const err = await res.json().catch(() => ({}))
        throw new Error(err._server_messages || err.message || 'Create failed')
      }

      state.formName = slug
      return slug
    }

    // Existing form — update all relevant fields in one call
    const res = await fetch(`/api/resource/NCE Form Definition/${state.formName}`, {
      method: 'PUT',
      headers,
      credentials: 'include',
      body: JSON.stringify({
        title: state.title,
        target_doctype: state.targetDoctype,
        grid_layout,
        grid_config,
      })
    })
    if (!res.ok) {
      const err = await res.json().catch(() => ({}))
      throw new Error(err._server_messages || err.message || 'Save failed')
    }

    return state.formName
  }

  async function load(): Promise<void> {
    try {
      const response = await fetch(`/api/resource/NCE Form Definition/${state.formName}`, {
        credentials: 'include'
      })
      if (!response.ok) return

      const data = await response.json()
      const resource = data.data

      state.title = resource.title || ''
      state.targetDoctype = resource.target_doctype || ''

      try {
        state.elements = resource.grid_layout ? JSON.parse(resource.grid_layout) : []
      } catch {
        state.elements = []
      }

      try {
        if (resource.grid_config) {
          state.gridConfig = JSON.parse(resource.grid_config)
        }
      } catch {
        state.gridConfig = { cellSize: 30, gap: 1 }
      }
    } catch {
      // fallback to defaults
    }
  }

  return {
    state,
    selectedElement,
    addElement,
    removeElement,
    updateElement,
    moveElement,
    resizeElement,
    selectElement,
    serialise,
    save,
    load
  }
}
