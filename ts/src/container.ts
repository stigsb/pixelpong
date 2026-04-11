export class Container {
  private services = new Map<string, unknown>();

  set<T>(key: string, value: T): void {
    this.services.set(key, value);
  }

  get<T>(key: string): T {
    const service = this.services.get(key);
    if (service === undefined) {
      throw new Error(`Service not found: ${key}`);
    }
    return service as T;
  }

  has(key: string): boolean {
    return this.services.has(key);
  }
}
