/**
 * ===== Каталог (/) =====
 *
 * frontend/src/pages/Catalog.jsx-ийн хувилбар.
 *
 * ЛОГИК НЬ ИЖИЛ: шүүлт/хайлт/хуудаслалт бүгд SERVER дээр хийгддэг — нэг
 * хуудсанд ердөө 8 ном татна.
 *
 * ГУРВАН ЗҮЙЛ ӨӨРЧЛӨГДСӨН:
 *
 * 1. ХУУДАСЛАЛТ → ТАСРАЛТГҮЙ ГҮЙЛГЭЛТ.
 *    Веб дээр ‹ 1 2 3 › товч дарж хуудас солино; ном бүрд ЗӨВХӨН тухайн хуудсын
 *    8 ном байна. Утсан дээр тэр жижиг товчнуудыг хуруугаар онох хэцүү тул
 *    жагсаалтын ЁРОО хүрэхэд дараагийн хуудсыг НЭМЖ ачаална.
 *    → өөрөөр хэлбэл `setBooks(next)` биш `setBooks([...prev, ...next])`.
 *
 * 2. ХАЙЛТ DEBOUNCE хийгдэв.
 *    Веб дээр товчлуур дарах бүрд шууд хүсэлт явдаг. Гэрийн Wi-Fi дээр тэр нь
 *    мэдэгдэхгүй; утасны сүлжээн дээр "Dune" гэж бичихэд 4 хүсэлт явж, эцсийнх
 *    нь хамгийн сүүлд ирнэ гэсэн баталгаа ч алга. 400мс хүлээгээд НЭГ хүсэлт
 *    явуулна.
 *
 * 3. FlatList ашиглав (веб дээрх .grid + .map биш).
 *    .map() нь 100 номыг 100 бүгдийг нь ЗЭРЭГ зурна. FlatList нь дэлгэцэнд
 *    харагдаж буйг нь л зурж, гүйлгэхэд дахин ашиглана — тиймээс жагсаалт
 *    урт болох тусам ялгаа нь томордог.
 */

import { Stack } from 'expo-router';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

import { borrowBook, normalizeBook, searchBooks, type Book } from '@/api/books';
import client, { apiError } from '@/api/client';
import { BookCard } from '@/components/BookCard';
import { FilterChips } from '@/components/FilterChips';
import { HeaderActions } from '@/components/HeaderActions';
import { SearchBox } from '@/components/SearchBox';
import { EmptyState, InlineLink, PageTitle, PrimaryLink } from '@/components/ui';
import { Colors } from '@/constants/theme';
import { useAuth } from '@/context/auth';

export default function CatalogScreen() {
  const { user, loading: authLoading } = useAuth();
  const insets = useSafeAreaInsets();

  // ---- шүүлтүүрийн төлөв (веб хувилбартай ижил) ----
  const [query, setQuery] = useState('');
  const [debouncedQuery, setDebouncedQuery] = useState('');
  const [category, setCategory] = useState('all');
  const [availability, setAvailability] = useState('all');
  const [categories, setCategories] = useState<string[]>([]);

  // ---- жагсаалтын төлөв ----
  const [books, setBooks] = useState<Book[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true); // эхний ачаалалт
  const [loadingMore, setLoadingMore] = useState(false); // ёроолд нэмж ачаалж буй
  const [refreshing, setRefreshing] = useState(false); // доош чирч сэргээж буй
  const [error, setError] = useState('');
  const [borrowingId, setBorrowingId] = useState<number | null>(null);

  /**
   * Хамгийн сүүлийн хүсэлтийн дугаар.
   *
   * Веб дээр үүнийг `let active = true` + cleanup-аар шийддэг байсан. Энд
   * хүсэлт нь effect-ээс гадна (ёроолд хүрэх, доош чирэх) ч эхэлдэг тул
   * cleanup хүрэлцэхгүй. Оронд нь хүсэлт бүрд дугаар өгөөд, хариу ирэхэд
   * "энэ хамгийн сүүлийнх мөн үү?" гэж шалгана. Хуучирсан хариуг хаяна.
   *
   * useRef ашигласан шалтгаан: энэ утга өөрчлөгдөхөд дэлгэц дахин зурагдах
   * ЁСГҮЙ. useState бол зурагдах байсан.
   */
  const requestId = useRef(0);

  // Хайлтыг 400мс саатуулна (дээрх 2-р тайлбар).
  useEffect(() => {
    const timer = setTimeout(() => setDebouncedQuery(query.trim()), 400);
    // Хэрэглэгч дараагийн үсгийг бичвэл өмнөх таймерыг цуцална — тиймээс
    // бичиж ДУУССАНЫ дараа л нэг удаа ажиллана.
    return () => clearTimeout(timer);
  }, [query]);

  // Ангиллыг нэг удаа татна (өөрчлөгддөггүй).
  useEffect(() => {
    client
      .get('/categories')
      .then((res) => setCategories(res.data.map((c: { name: string }) => c.name)))
      .catch(() => {
        // Ангилал ирэхгүй бол чипсийн мөр "Бүгд" ганцаараа үлдэнэ — ном үзэхэд
        // саад болохгүй тул алдааг харуулах шаардлагагүй.
      });
  }, []);

  /**
   * Нэг хуудас татах.
   *
   * mode: 'replace' — шүүлт солигдсон, эхнээс нь
   *       'append'  — ёроолд хүрсэн, ард нь нэмнэ
   *       'refresh' — доош чирсэн, эхнээс нь (гэхдээ дэлгэц цэвэрлэхгүй)
   */
  const load = useCallback(
    async (targetPage: number, mode: 'replace' | 'append' | 'refresh') => {
      // Нэвтрээгүй бол хүсэлт огт явуулахгүй — /books нь auth шаарддаг тул
      // 401 авах нь дэмий (веб хувилбартай ижил бодлого).
      if (!user) {
        setBooks([]);
        setTotal(0);
        setLoading(false);
        return;
      }

      const id = ++requestId.current;

      if (mode === 'append') setLoadingMore(true);
      else if (mode === 'refresh') setRefreshing(true);
      else setLoading(true);

      try {
        // ХАЙЛТЫН ҮГ БАЙВАЛ: embedding хайлт (/books/search).
        // Backend cosine-оор эрэмбэлсэн 10 номыг буцаадаг — хуудаслалт байхгүй,
        // ангилал/байдлын шүүлтийг ч мэдэхгүй. Тиймээс шүүлтийг энд, ирсэн
        // 10 ном дээр л хийнэ (жижиг массив тул зардалгүй).
        if (debouncedQuery) {
          let next = await searchBooks(debouncedQuery);
          if (id !== requestId.current) return; // хуучирсан хариу — хаяна

          if (category !== 'all') next = next.filter((b) => b.category === category);
          if (availability === 'available') next = next.filter((b) => b.available > 0);
          else if (availability === 'checked-out') next = next.filter((b) => b.available === 0);

          setBooks(next);
          setPage(1);
          setLastPage(1); // ёроолд хүрсэн ч дахин ачаалахгүй
          setTotal(next.length);
          setError('');
          return;
        }

        // ХАЙЛТЫН ҮГ БАЙХГҮЙ: хуучин зам — шүүлт/хуудаслалт SERVER дээр.
        const params: Record<string, string | number> = { page: targetPage };
        if (category !== 'all') params.category = category;
        if (availability !== 'all') params.availability = availability;

        const res = await client.get('/books', { params });
        if (id !== requestId.current) return; // хуучирсан хариу — хаяна

        const next: Book[] = res.data.data.map(normalizeBook);

        setBooks((prev) => (mode === 'append' ? [...prev, ...next] : next));
        setPage(targetPage);
        setLastPage(res.data.meta.last_page);
        setTotal(res.data.meta.total);
        setError('');
      } catch (err) {
        if (id !== requestId.current) return;
        setError(apiError(err, 'Өгөгдөл ачаалж чадсангүй. Backend асаалттай юу?'));
      } finally {
        if (id === requestId.current) {
          setLoading(false);
          setLoadingMore(false);
          setRefreshing(false);
        }
      }
    },
    [user, category, availability, debouncedQuery]
  );

  /**
   * Шүүлт/хайлт/хэрэглэгч өөрчлөгдвөл эхний хуудсаас дахин эхэлнэ.
   *
   * `load` нь useCallback-тай бөгөөд түүний хамаарал (user, category,
   * availability, debouncedQuery) өөрчлөгдөхөд ШИНЭ функц болдог. Тиймээс
   * [load] гэж бичихэд веб дээрх [page, category, availability, query, user]
   * жагсаалттай яг ижил үр дүн гарна — гэхдээ давхардалгүй.
   */
  useEffect(() => {
    load(1, 'replace');
  }, [load]);

  /** Жагсаалтын ёроолд хүрэхэд дараагийн хуудсыг НЭМЖ ачаална. */
  const loadMore = () => {
    // Аль хэдийн ачаалж байгаа, эсвэл сүүлийн хуудсан дээр байвал алгасна.
    // Үүнгүй бол FlatList ёроол руу ойртох бүрд олон хүсэлт зэрэг явуулна.
    if (loading || loadingMore || refreshing || page >= lastPage) return;
    load(page + 1, 'append');
  };

  /**
   * Ном зээлэх.
   *
   * Веб дээр `alert()` ашигласан. RN-д `alert()` байхгүй — Alert.alert().
   * Санамж: backend нь 202 Accepted буцаадаг (queue) тул "зээллээ" гэдэг нь
   * "хүсэлтийг хүлээж авлаа" гэсэн үг.
   */
  const handleBorrow = useCallback(async (bookId: number) => {
    setBorrowingId(bookId);
    try {
      const dueDate = await borrowBook(bookId);
      // Серверээс дахин татахгүйгээр локал тоог 1 бууруулна.
      setBooks((prev) =>
        prev.map((b) => (b.id === bookId ? { ...b, available: b.available - 1 } : b))
      );
      Alert.alert('Зээллээ', `Буцаах хугацаа: ${dueDate}`);
    } catch (err) {
      Alert.alert('Зээлэхэд алдаа гарлаа', apiError(err, 'Зээлэхэд алдаа гарлаа.'));
    } finally {
      setBorrowingId(null);
    }
  }, []);

  const renderItem = useCallback(
    ({ item }: { item: Book }) => (
      <BookCard
        book={item}
        signedIn={!!user}
        onBorrow={handleBorrow}
        borrowing={borrowingId === item.id}
      />
    ),
    [user, handleBorrow, borrowingId]
  );

  // Сесс уншиж дуустал юу ч шийдэхгүй. Үүнгүй бол нэвтэрсэн хэрэглэгчид
  // "Нэвтэрнэ үү" дэлгэц хормын зуур харагдана.
  if (authLoading) {
    return (
      <View style={s.center}>
        <ActivityIndicator color={Colors.primary} />
      </View>
    );
  }

  // Нэвтрээгүй үед: каталогийн оронд нэвтрэх урилга.
  // Ном бүр компанид харьяалагддаг тул хэн болохыг мэдэхгүйгээр юу харуулахыг
  // тодорхойлох боломжгүй.
  if (!user) {
    return (
      <View style={s.page}>
        <Stack.Screen options={{ headerRight: () => <HeaderActions /> }} />
        <PageTitle>Library Catalog</PageTitle>
        <EmptyState>
          Номын жагсаалтыг үзэхийн тулд нэвтэрнэ үү. Та зөвхөн өөрийн байгууллагад
          харьяалагдах номыг харах боломжтой.
        </EmptyState>
        <View style={s.authActions}>
          <PrimaryLink href="/signin" title="Нэвтрэх" />
          <InlineLink href="/register" title="Бүртгүүлэх" />
        </View>
      </View>
    );
  }

  return (
    <View style={s.flex}>
      {/* Дэлгэцийн ДОТРООС толгойн тохиргоог өөрчилнө. Веб дээр Header нь
          App.jsx-д байсан бол энд дэлгэц бүр өөрийн товчоо зарлана. */}
      <Stack.Screen options={{ headerRight: () => <HeaderActions /> }} />

      {/* ХАЙЛТ + ШҮҮЛТҮҮР нь FlatList-ийн ГАДНА, тогтмол байрлана.
          Яагаад ListHeaderComponent биш вэ: жагсаалт дахин зурагдах бүрд
          толгой нь дахин mount хийгдэж, бичиж байсан TextInput фокусаа алдаж,
          гар хаагддаг. Гадна байрлуулснаар тэр асуудал үүсэхгүй бөгөөд
          гүйлгэж байхад ч хайлт гарт хэвээр үлдэнэ. */}
      <View style={s.controls}>
        <SearchBox value={query} onChange={setQuery} />
        <FilterChips
          categories={categories}
          category={category}
          onCategory={setCategory}
          availability={availability}
          onAvailability={setAvailability}
        />
        <Text style={s.count}>
          {total} ном
          {/* Админ бүх компанийн номыг харна — тэр ялгааг тодорхой хэлнэ. */}
          {user.role === 'admin'
            ? ' · бүх байгууллага'
            : user.company?.name
              ? ` · ${user.company.name}`
              : ''}
        </Text>
      </View>

      {loading ? (
        <View style={s.center}>
          <ActivityIndicator color={Colors.primary} />
        </View>
      ) : error ? (
        <View style={s.page}>
          <EmptyState>{error}</EmptyState>
        </View>
      ) : (
        <FlatList
          data={books}
          keyExtractor={(b) => String(b.id)}
          renderItem={renderItem}
          // Веб дээрх .grid (CSS grid) → RN-д баганын тоо.
          numColumns={2}
          columnWrapperStyle={s.column}
          contentContainerStyle={[
            s.listContent,
            // Доод "safe area" (iPhone-ий зураас, Android-ын навигацийн мөр)
            // сүүлийн картыг халхлахаас сэргийлнэ.
            { paddingBottom: insets.bottom + 24 },
          ]}
          // Ёроолоос 50% үлдэхэд дараагийн хуудсыг эхлүүлнэ — хэрэглэгч
          // ёроолд хүрэхээс өмнө ачаалагдсан байхын тулд.
          onEndReached={loadMore}
          onEndReachedThreshold={0.5}
          ListFooterComponent={
            loadingMore ? <ActivityIndicator style={s.footer} color={Colors.primary} /> : null
          }
          ListEmptyComponent={<EmptyState>Шүүлтэд тохирох ном алга.</EmptyState>}
          // Доош чирж сэргээх — вебэд байхгүй, утсан дээр стандарт үйлдэл.
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={() => load(1, 'refresh')}
              tintColor={Colors.primary}
            />
          }
          // Гүйлгэж эхлэхэд гарыг хаана — эс бөгөөс хайлтын гар жагсаалтын
          // хагасыг халхалсан хэвээр үлдэнэ.
          keyboardDismissMode="on-drag"
        />
      )}
    </View>
  );
}

const s = StyleSheet.create({
  flex: { flex: 1 },
  page: { flex: 1, paddingHorizontal: 16, paddingTop: 8 },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  controls: { paddingHorizontal: 16, paddingTop: 8, paddingBottom: 12, gap: 12 },
  count: { fontSize: 13, color: Colors.textMuted },
  listContent: { paddingHorizontal: 16, gap: 20 },
  column: { gap: 16 },
  footer: { paddingVertical: 20 },
  authActions: { flexDirection: 'row', alignItems: 'center', gap: 16 },
});
