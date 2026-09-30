/**
 * ===== Folio дизайн систем (mobile) =====
 *
 * Энэ файл нь frontend/src/index.css дэх `:root` токенуудын ЯГ ХУУЛБАР.
 * React Native-д CSS хувьсагч (var(--bg)) байхгүй тул ердийн TS объект болгов.
 *
 * Веб дээр: color: var(--text-muted)
 * Энд:      color: Colors.textMuted
 *
 * Хоёр талд ижил өнгө байлгах нь чухал — өнгө өөрчлөгдвөл ХОЁУЛАНГ нь засна.
 */

import { Platform, type ViewStyle } from 'react-native';

export const Colors = {
  // Суурь өнгө
  bg: '#fff', // TEMP: шалгалтад түр цагаан болгов. Буцаах: '#f4f2ec' (цайвар шаргал дэвсгэр)
  surface: '#faf9f5', // карт зэрэг цайвар гадаргуу
  text: '#2c2b27', // үндсэн бараан текст
  textMuted: '#8b897e', // бүдэг/тайлбар текст
  border: '#e4e2d8', // нимгэн зураас

  // Ногоон accent (лого, үндсэн товч)
  primary: '#2f5233',
  primaryHover: '#264429', // хүрэлтийн үеийн (pressed) бараан хувилбар

  // Бүдэг гадаргуу — pill--out, btn-borrow, auth-demo гурвуулаа ашиглана
  surfaceMuted: '#ecebe3',

  // Амжилт / мэдээлэл (боломжтой badge, ногоон мэдэгдэл)
  availBg: '#c9e8d6',
  availText: '#2f5233',

  // Алдаа (улаан мэдэгдэл)
  errBg: '#f7dcd8',
  errText: '#8c2f22',

  // Идэвхтэй шүүлтүүр
  activeBg: '#d8dccf',

  white: '#fff',
} as const;

/** Булангийн дугуйрал — index.css дэх --radius* токенууд. */
export const Radius = {
  md: 12,
  sm: 8,
  /** Бүрэн дугуйрсан ирмэг. Веб дээр 999px байсан. */
  pill: 999,
} as const;

/**
 * Сүүдэр. CSS-ийн нэг мөр `box-shadow` энд ХОЁР систем болж хуваагдана:
 *   iOS     → shadowColor / shadowOffset / shadowOpacity / shadowRadius
 *   Android → elevation (ганц тоо; чиглэл, өнгө сонгох боломжгүй)
 * Тиймээс Platform.select-ээр хоёуланг нь өгнө.
 */
export const Shadow: ViewStyle = Platform.select<ViewStyle>({
  ios: {
    shadowColor: '#000',
    shadowOffset: { width: 0, height: 6 },
    shadowOpacity: 0.15,
    shadowRadius: 10,
  },
  default: { elevation: 4 },
})!;

/** Үсгийн хэмжээ — веб дээр давтагдаж байсан утгууд. */
export const Font = {
  /**
   * Тоо/дугаар харуулах моно фонт (зээллэгийн #42 гэх мэт).
   * Ижил өргөнтэй тул 1 ба l, 0 ба O андуурагдахгүй.
   */
  mono: Platform.select({ ios: 'Menlo', android: 'monospace', default: 'monospace' }),
} as const;

/**
 * Ангилал бүрийн хавтасны градиент — frontend/src/data/books.js-ээс.
 *
 * Веб дээр: "linear-gradient(160deg, #7a4325, #3d2417)" гэсэн НЭГ мөр.
 * Энд <LinearGradient colors={[...]} /> нь өнгөнүүдийг МАССИВ хэлбэрээр авдаг
 * тул мөрийг задалж бичив.
 */
export const COVER_GRADIENTS: Record<string, readonly [string, string]> = {
  Fiction: ['#7a4325', '#3d2417'],
  Mystery: ['#3a4a63', '#1f2a3c'],
  'Science Fiction': ['#35566b', '#1c2f3a'],
  History: ['#6a5a34', '#362f1c'],
  Philosophy: ['#4a3a63', '#26203c'],
  Memoir: ['#6b3040', '#371822'],
  Science: ['#2f5a55', '#17302d'],
};

/** Ангилал нь жагсаалтад байхгүй (эсвэл null) үеийн нөөц градиент. */
export const FALLBACK_GRADIENT: readonly [string, string] = ['#555', '#222'];

/**
 * CSS-ийн `160deg` өнцгийн ойролцоо утга.
 * LinearGradient нь өнцөг биш, ЦЭГ хоёроор чиглэлээ авдаг: (0,0) нь зүүн дээд,
 * (1,1) нь баруун доод булан. 160° нь бараг доош, бага зэрэг баруун тийш
 * хазайсан тул x нь 1 биш 0.35 хүрнэ.
 */
export const GRADIENT_START = { x: 0, y: 0 } as const;
export const GRADIENT_END = { x: 0.35, y: 1 } as const;

/**
 * Картын хавтасны ард харагдах зураг (Cloudinary дээрх folio/card-background).
 * URL доторх хувиргалт: ar_3:4 нь хавтасны харьцаатай таарна, f_auto/q_auto нь
 * формат/чанарыг төхөөрөмжид тохируулж татах хэмжээг багасгана.
 */
export const CARD_BACKGROUND_URL =
  'https://res.cloudinary.com/dv0hxjoqv/image/upload/w_600,ar_3:4,c_fill,f_auto,q_auto/folio/card-background';
