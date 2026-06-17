import { createClient } from '@supabase/supabase-js'

const supabaseUrl = 'https://voxqwctcgszcolkhxphb.supabase.co'
const supabaseKey = 'sb_publishable_7BrnHILmtEn0sefXhugGvg_5clKvv2Z'

export const supabase = createClient(supabaseUrl, supabaseKey)
