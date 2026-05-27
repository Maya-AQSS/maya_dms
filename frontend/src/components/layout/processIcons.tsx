/**
 * Catálogo de iconos disponibles para procesos. Usa lucide-react (tree-shakeable).
 * El backend almacena solo el slug — este mapa resuelve el slug al componente.
 *
 * Para añadir un icono: importarlo de lucide-react, añadir entrada en PROCESS_ICONS
 * con el slug en kebab-case. El slug se guarda en BD, no cambiar slugs existentes.
 */
import type { ReactNode } from 'react'
import {
  Activity, AlertTriangle, Archive, Award,
  BarChart, BarChart2, Bell, Book, BookMarked, BookOpen,
  Briefcase, Calendar, CheckCircle, Clock,
  Clipboard, ClipboardCheck, ClipboardList,
  Cloud, CloudDownload, CloudUpload,
  Code, Code2, Compass, Copy, Cpu,
  Database, DollarSign, Download,
  Edit3, Eye,
  File, FileCheck, FilePlus, FileSearch, FileSignature, FileText,
  Filter, Flag, Folder, FolderOpen, FolderPlus,
  GitBranch, Globe, GraduationCap,
  Handshake, HardDrive, Hash, Heart, HelpCircle, Home,
  Image, Inbox, Info,
  Key,
  LayoutDashboard, LayoutGrid, LayoutList, Layers, Library,
  Lightbulb, LineChart, Link, List, ListChecks, ListOrdered,
  Lock,
  Mail, MailOpen, Map, MapPin, Megaphone,
  MessageCircle, MessageSquare, Monitor,
  Network, Newspaper,
  Package, Pencil, PersonStanding, Phone, PhoneCall,
  PieChart, Presentation, Printer,
  RefreshCw, Rocket, Rss,
  Save, ScrollText, Search, Send, Server,
  Settings, Share2, Shield, ShieldCheck, ShoppingCart,
  SlidersHorizontal, Smartphone, Smile, Star,
  Table2, Tag, Tags, Target, Terminal,
  TrendingDown, TrendingUp, Trophy, Truck,
  Unlock, Upload, UserCheck, UserPlus, UserX, Users, Users2,
  Video,
  Wallet, Wifi, Workflow, Wrench,
  Zap,
} from 'lucide-react'

const SIZE = 16

const PROCESS_ICONS: Record<string, ReactNode> = {
  // — Estrategia y dirección —
  'target':         <Target size={SIZE} />,
  'flag':           <Flag size={SIZE} />,
  'trending-up':    <TrendingUp size={SIZE} />,
  'trending-down':  <TrendingDown size={SIZE} />,
  'activity':       <Activity size={SIZE} />,
  'alert-triangle': <AlertTriangle size={SIZE} />,
  'check-circle':   <CheckCircle size={SIZE} />,
  'lightbulb':      <Lightbulb size={SIZE} />,
  'compass':        <Compass size={SIZE} />,
  'bar-chart':      <BarChart size={SIZE} />,
  'bar-chart-2':    <BarChart2 size={SIZE} />,
  'line-chart':     <LineChart size={SIZE} />,
  'pie-chart':      <PieChart size={SIZE} />,
  'presentation':   <Presentation size={SIZE} />,
  'award':          <Award size={SIZE} />,
  'star':           <Star size={SIZE} />,
  'zap':            <Zap size={SIZE} />,
  'rocket':         <Rocket size={SIZE} />,
  'trophy':         <Trophy size={SIZE} />,

  // — Comunicación —
  'message-square': <MessageSquare size={SIZE} />,
  'message-circle': <MessageCircle size={SIZE} />,
  'inbox':          <Inbox size={SIZE} />,
  'send':           <Send size={SIZE} />,
  'mail':           <Mail size={SIZE} />,
  'mail-open':      <MailOpen size={SIZE} />,
  'phone':          <Phone size={SIZE} />,
  'phone-call':     <PhoneCall size={SIZE} />,
  'bell':           <Bell size={SIZE} />,
  'megaphone':      <Megaphone size={SIZE} />,
  'rss':            <Rss size={SIZE} />,

  // — Documentos y archivos —
  'file-text':       <FileText size={SIZE} />,
  'file':            <File size={SIZE} />,
  'file-check':      <FileCheck size={SIZE} />,
  'file-plus':       <FilePlus size={SIZE} />,
  'file-search':     <FileSearch size={SIZE} />,
  'file-signature':  <FileSignature size={SIZE} />,
  'folder':          <Folder size={SIZE} />,
  'folder-open':     <FolderOpen size={SIZE} />,
  'folder-plus':     <FolderPlus size={SIZE} />,
  'archive':         <Archive size={SIZE} />,
  'download':        <Download size={SIZE} />,
  'upload':          <Upload size={SIZE} />,
  'copy':            <Copy size={SIZE} />,
  'printer':         <Printer size={SIZE} />,
  'save':            <Save size={SIZE} />,
  'clipboard-check': <ClipboardCheck size={SIZE} />,
  'clipboard-list':  <ClipboardList size={SIZE} />,
  'clipboard':       <Clipboard size={SIZE} />,
  'book-open':       <BookOpen size={SIZE} />,
  'library':         <Library size={SIZE} />,
  'book':            <Book size={SIZE} />,
  'newspaper':       <Newspaper size={SIZE} />,
  'scroll-text':     <ScrollText size={SIZE} />,
  'book-marked':     <BookMarked size={SIZE} />,

  // — Personas y usuarios —
  'users':           <Users size={SIZE} />,
  'users-2':         <Users2 size={SIZE} />,
  'user-check':      <UserCheck size={SIZE} />,
  'user-plus':       <UserPlus size={SIZE} />,
  'user-x':          <UserX size={SIZE} />,
  'graduation-cap':  <GraduationCap size={SIZE} />,
  'smile':           <Smile size={SIZE} />,
  'heart':           <Heart size={SIZE} />,
  'handshake':       <Handshake size={SIZE} />,
  'person-standing': <PersonStanding size={SIZE} />,

  // — Tecnología e infraestructura —
  'monitor':        <Monitor size={SIZE} />,
  'cpu':            <Cpu size={SIZE} />,
  'video':          <Video size={SIZE} />,
  'server':         <Server size={SIZE} />,
  'database':       <Database size={SIZE} />,
  'globe':          <Globe size={SIZE} />,
  'wifi':           <Wifi size={SIZE} />,
  'smartphone':     <Smartphone size={SIZE} />,
  'terminal':       <Terminal size={SIZE} />,
  'code':           <Code size={SIZE} />,
  'code-2':         <Code2 size={SIZE} />,
  'network':        <Network size={SIZE} />,
  'cloud':          <Cloud size={SIZE} />,
  'cloud-upload':   <CloudUpload size={SIZE} />,
  'cloud-download': <CloudDownload size={SIZE} />,
  'hard-drive':     <HardDrive size={SIZE} />,
  'layers':         <Layers size={SIZE} />,

  // — Organización y flujo —
  'calendar':         <Calendar size={SIZE} />,
  'clock':            <Clock size={SIZE} />,
  'home':             <Home size={SIZE} />,
  'link':             <Link size={SIZE} />,
  'settings':         <Settings size={SIZE} />,
  'wrench':           <Wrench size={SIZE} />,
  'shield':           <Shield size={SIZE} />,
  'shield-check':     <ShieldCheck size={SIZE} />,
  'key':              <Key size={SIZE} />,
  'lock':             <Lock size={SIZE} />,
  'unlock':           <Unlock size={SIZE} />,
  'layout-dashboard': <LayoutDashboard size={SIZE} />,
  'layout-grid':      <LayoutGrid size={SIZE} />,
  'layout-list':      <LayoutList size={SIZE} />,
  'table-2':          <Table2 size={SIZE} />,
  'list':             <List size={SIZE} />,
  'list-checks':      <ListChecks size={SIZE} />,
  'list-ordered':     <ListOrdered size={SIZE} />,
  'filter':           <Filter size={SIZE} />,
  'tags':             <Tags size={SIZE} />,
  'tag':              <Tag size={SIZE} />,
  'sliders':          <SlidersHorizontal size={SIZE} />,
  'workflow':         <Workflow size={SIZE} />,
  'git-branch':       <GitBranch size={SIZE} />,
  'share-2':          <Share2 size={SIZE} />,
  'briefcase':        <Briefcase size={SIZE} />,

  // — Economía y recursos —
  'dollar-sign':   <DollarSign size={SIZE} />,
  'wallet':        <Wallet size={SIZE} />,
  'package':       <Package size={SIZE} />,
  'truck':         <Truck size={SIZE} />,
  'shopping-cart': <ShoppingCart size={SIZE} />,

  // — Búsqueda e información —
  'search':      <Search size={SIZE} />,
  'help-circle': <HelpCircle size={SIZE} />,
  'info':        <Info size={SIZE} />,
  'eye':         <Eye size={SIZE} />,
  'refresh-cw':  <RefreshCw size={SIZE} />,
  'edit-3':      <Edit3 size={SIZE} />,
  'pencil':      <Pencil size={SIZE} />,
  'map':         <Map size={SIZE} />,
  'map-pin':     <MapPin size={SIZE} />,
  'image':       <Image size={SIZE} />,
  'hash':        <Hash size={SIZE} />,
}

/** Lista ordenada de slugs disponibles para el selector de iconos. */
export const PROCESS_ICON_SLUGS = Object.keys(PROCESS_ICONS) as string[]

/**
 * Resuelve un slug a un nodo React. Si el slug no existe, devuelve el
 * icono fallback (Folder).
 */
export function getProcessIcon(slug: string | null | undefined): ReactNode {
  if (!slug) return <Folder size={SIZE} />
  return PROCESS_ICONS[slug] ?? <Folder size={SIZE} />
}
