function AuctionTimer({ endDate }) {
  const [now, setNow] = React.useState(Date.now());
  React.useEffect(() => { const id = setInterval(() => setNow(Date.now()), 1000); return () => clearInterval(id); }, []);
  const diff = new Date(endDate).getTime() - now;
  if (diff <= 0) return <span>Terminée</span>;
  const minutes = Math.floor(diff / 60000);
  return <span>{minutes} min restantes</span>;
}
