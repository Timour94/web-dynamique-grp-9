function NotificationBell({ apiUrl }) {
  const [count, setCount] = React.useState(0);
  React.useEffect(() => { fetch(apiUrl).then(r => r.json()).then(d => setCount(d.count || 0)); }, [apiUrl]);
  return <span className="badge">{count}</span>;
}
