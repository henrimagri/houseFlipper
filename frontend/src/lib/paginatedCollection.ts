type Page<T> = { data: T[]; meta: { last_page: number } };

/** Fetch a complete Laravel collection, publishing only the latest request. */
export class PaginatedCollection<T> {
  private controller?: AbortController;

  cancel() {
    this.controller?.abort();
  }

  async load(fetchPage: (page: number, perPage: number, signal: AbortSignal) => Promise<Page<T>>): Promise<T[] | undefined> {
    this.cancel();
    const controller = new AbortController();
    this.controller = controller;
    const items: T[] = [];
    let page = 1;
    let lastPage = 1;
    try {
      do {
        const response = await fetchPage(page, 100, controller.signal);
        if (controller.signal.aborted) return;
        if (!Array.isArray(response.data) || !Number.isSafeInteger(response.meta?.last_page) || response.meta.last_page < page) {
          throw new Error('Resposta de paginação inválida.');
        }
        items.push(...response.data);
        lastPage = response.meta.last_page;
        page++;
      } while (page <= lastPage);
      return items;
    } catch (error) {
      if (!controller.signal.aborted) throw error;
    }
  }
}
