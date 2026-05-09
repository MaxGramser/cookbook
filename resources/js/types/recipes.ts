export type Unit = 'g' | 'ml' | 'tsp' | 'tbsp' | 'piece' | null;

export type RecipeIngredient = {
    id: number;
    recipe_id: number;
    section: string | null;
    position: number;
    name: string;
    quantity: number | null;
    unit: Unit;
    raw_text: string | null;
};

export type RecipeStep = {
    id: number;
    recipe_id: number;
    section: string | null;
    position: number;
    body: string;
    timer_minutes: number | null;
};

export type TagGroup = 'meal_type' | 'cuisine' | 'attribute';

export type TagColor = 'cream' | 'lime' | 'pink' | 'sky' | 'accent' | 'ink';

export type Tag = {
    id: number;
    group: TagGroup;
    slug: string;
    name: string;
    color: TagColor;
    is_system?: boolean;
};

export type RecipeSummary = {
    id: number;
    title: string;
    image_path: string | null;
    cook_time_minutes: number | null;
    servings: number;
    is_starred: boolean;
    cooked_count: number;
    last_cooked_at: string | null;
    tags: Tag[];
};

export type RecipeListFilters = {
    q: string;
    starred: boolean;
    cooked: boolean;
    time: 'quick' | 'medium' | 'long' | null;
    tag_ids: number[];
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
    total?: number;
};

export type Recipe = {
    id: number;
    user_id: number;
    title: string;
    source_url: string | null;
    image_path: string | null;
    servings: number;
    cook_time_minutes: number | null;
    notes: string | null;
    is_starred?: boolean;
    cooked_count?: number;
    last_cooked_at?: string | null;
    ingredients: RecipeIngredient[];
    steps: RecipeStep[];
    tags?: Tag[];
};

export type CookSessionDetail = {
    id: number;
    servings_multiplier: number;
    notes: string | null;
    started_at: string;
    completed_at: string | null;
    paused_at: string | null;
    paused_seconds: number;
    recipe: {
        id: number;
        title: string;
        image_path: string | null;
        servings: number;
        cook_time_minutes: number | null;
        ingredients: RecipeIngredient[];
        steps: RecipeStep[];
    };
    checked_ingredient_ids: number[];
    checked_step_ids: number[];
};

export type CookSessionSummary = {
    id: number;
    recipe_id: number;
    servings_multiplier: number;
    completed_at: string | null;
    started_at?: string;
    notes: string | null;
    recipe?: { id: number; title: string; image_path: string | null };
};

export type GrocerySessionRecipe = {
    id: number;
    title: string;
    image_path: string | null;
    servings: number;
    ingredients: RecipeIngredient[];
};

export type GrocerySessionDetail = {
    id: number;
    phase: 'home' | 'shopping';
    started_at: string;
    completed_at: string | null;
    subject_type: 'recipe' | 'shortlist';
    subject: { id: number; title: string };
    recipes: GrocerySessionRecipe[];
    checks: { id: number; phase: 'home' | 'shopping' }[];
};

export type ShortlistSidebarItem = {
    id: number;
    name: string;
    color: string | null;
    recipe_count: number;
};

export type ShortlistRecipe = RecipeSummary & {
    pivot: {
        position: number;
        note: string | null;
    };
};

export type ShortlistShareInfo = {
    token: string;
    url: string;
    expires_at: string | null;
};

export type ShortlistDetail = {
    id: number;
    name: string;
    color: string | null;
    active_share: ShortlistShareInfo | null;
    recipes: ShortlistRecipe[];
};

export type GrocerySessionSummary = {
    id: number;
    recipe_id: number;
    phase: 'home' | 'shopping';
    completed_at: string | null;
    started_at?: string;
    recipe?: { id: number; title: string; image_path: string | null };
};
